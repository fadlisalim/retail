<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Ratings & reviews (spec §12). Only a customer who actually bought the item on a
 * completed order can review it, exactly once. Admins can moderate but the raw
 * customer rating value is never silently altered — every moderation action is
 * audited, and hidden reviews are excluded from the aggregate.
 */
class ReviewService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /** Order items the user is eligible to review (bought, completed, not yet reviewed). */
    public function reviewableItems(User $user)
    {
        return OrderItem::whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', 'completed'))
            ->whereDoesntHave('review')
            ->with('product')
            ->get();
    }

    public function create(User $user, OrderItem $orderItem, array $data): Review
    {
        // Ownership + completion + one-review-per-item guards.
        if ($orderItem->order->user_id !== $user->id) {
            throw ValidationException::withMessages(['review' => 'Item pesanan ini bukan milik Anda.']);
        }
        if ($orderItem->order->status->value !== 'completed') {
            throw ValidationException::withMessages(['review' => 'Hanya pesanan selesai yang dapat direview.']);
        }
        if ($orderItem->review()->exists()) {
            throw ValidationException::withMessages(['review' => 'Item ini sudah pernah direview.']);
        }

        $review = Review::create([
            'product_id' => $orderItem->product_id,
            'user_id' => $user->id,
            'order_item_id' => $orderItem->id,
            'rating' => max(1, min(5, (int) $data['rating'])),
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_verified_purchase' => true,
            'is_visible' => true,
            'edit_deadline_at' => now()->addDays(7),
        ]);

        $this->recomputeRating($orderItem->product);

        return $review;
    }

    public function update(Review $review, array $data): Review
    {
        if (! $review->isEditable()) {
            throw ValidationException::withMessages(['review' => 'Masa edit review sudah berakhir.']);
        }

        $review->update([
            'rating' => max(1, min(5, (int) $data['rating'])),
            'title' => $data['title'] ?? $review->title,
            'comment' => $data['comment'] ?? $review->comment,
        ]);

        $this->recomputeRating($review->product);

        return $review;
    }

    public function setVisibility(Review $review, bool $visible, ?User $actor = null): void
    {
        $this->audit->log($visible ? 'review.shown' : 'review.hidden', $review,
            ['is_visible' => $review->is_visible], ['is_visible' => $visible], $actor);

        $review->update(['is_visible' => $visible]);
        $this->recomputeRating($review->product);
    }

    public function reply(Review $review, string $reply, User $actor): void
    {
        $review->update(['admin_reply' => $reply, 'replied_by' => $actor->id]);
        $this->audit->log('review.replied', $review, [], ['reply' => $reply], $actor);
    }

    public function markHelpful(Review $review, User $user): void
    {
        $created = $review->helpfulness()->firstOrCreate(['user_id' => $user->id]);
        if ($created->wasRecentlyCreated) {
            $review->increment('helpful_count');
        }
    }

    /** Recompute average + count from VISIBLE reviews only. */
    public function recomputeRating(Product $product): void
    {
        $agg = $product->reviews()->where('is_visible', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        // rating_* are guarded (non-fillable) counters, so bypass mass assignment.
        $product->forceFill([
            'rating_avg' => round((float) ($agg->avg_rating ?? 0), 2),
            'rating_count' => (int) ($agg->total ?? 0),
        ])->save();
    }

    /** Rating distribution [5=>n,4=>n,...] for the product page. */
    public function distribution(Product $product): array
    {
        $rows = $product->reviews()->where('is_visible', true)
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return collect(range(5, 1))->mapWithKeys(fn ($r) => [$r => (int) ($rows[$r] ?? 0)])->all();
    }
}
