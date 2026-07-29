<?php

namespace App\View\Composers;

use App\Enums\AffiliateStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Review;
use Illuminate\View\View;

/**
 * Provides "needs attention" counts for the admin sidebar so menu items with an
 * actionable inbox show a badge. Only genuinely actionable queues get a count —
 * management pages (products, categories, …) never do.
 */
class AdminMenuComposer
{
    public function compose(View $view): void
    {
        $view->with('menuBadges', [
            // Orders waiting on the admin: set shipping cost, or verify a payment proof.
            'orders' => Order::where(fn ($q) => $q
                ->where('status', OrderStatus::AwaitingShippingConfirmation->value)
                ->orWhere('payment_status', PaymentStatus::AwaitingVerification->value))
                ->count(),
            // Brand-new quotation requests needing a first response.
            'quotations' => Quotation::where('status', QuotationStatus::New->value)->count(),
            // Reviews with an unresolved report to moderate.
            'reviews' => Review::whereHas('reports', fn ($q) => $q->where('resolved', false))->count(),
            // Affiliate applications awaiting verification.
            'affiliates' => Affiliate::where('status', AffiliateStatus::Pending->value)->count(),
            // Unread incoming WhatsApp messages in the inbox.
            'wachat' => \App\Models\WaMessage::where('direction', 'in')->where('is_read', false)->count(),
            // Unread on-site Chat Toko messages from customers.
            'sitechat' => \App\Models\SiteChatMessage::where('direction', 'in')->where('is_read', false)->count(),
        ]);
    }
}
