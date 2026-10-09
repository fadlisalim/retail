<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Request-for-quotation flow (spec §20). A customer submits an RFQ; sales prices
 * it (each revision snapshotted); once approved it converts into a real order.
 */
class QuotationService
{
    public function __construct(
        private readonly TaxService $tax,
        private readonly InvoiceService $invoices,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<int,array{name:string,quantity:int,product_id?:int,note?:string}> $items */
    public function createRfq(array $data, array $items, ?int $userId = null): Quotation
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $quotation = Quotation::create([
                'rfq_number' => 'RFQ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'public_token' => (string) Str::uuid(),
                'user_id' => $userId,
                'status' => QuotationStatus::New,
                'contact_name' => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'npwp' => $data['npwp'] ?? null,
                'requester_role' => $data['requester_role'] ?? null,
                'decision_role' => $data['decision_role'] ?? null,
                'project_name' => $data['project_name'] ?? null,
                'project_type' => $data['project_type'] ?? null,
                'project_status' => $data['project_status'] ?? null,
                'funding_source' => $data['funding_source'] ?? null,
                'budget_range' => $data['budget_range'] ?? null,
                'project_location' => $data['project_location'] ?? null,
                'unit_scale' => $data['unit_scale'] ?? null,
                'procurement_target' => $data['procurement_target'] ?? null,
                'needs_installation' => (bool) ($data['needs_installation'] ?? false),
                'needs_survey' => (bool) ($data['needs_survey'] ?? false),
                'needs_tender_docs' => (bool) ($data['needs_tender_docs'] ?? false),
                'technical_notes' => $data['technical_notes'] ?? null,
                'requirements' => ! empty($data['requirements']) ? $data['requirements'] : null,
            ]);

            foreach ($items as $item) {
                $quotation->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'name' => $item['name'],
                    'note' => $item['note'] ?? null,
                    'quantity' => max(1, (int) $item['quantity']),
                    'unit_price' => 0,
                    'line_total' => 0,
                ]);
            }

            return $quotation->fresh('items');
        });
    }

    /**
     * Penawaran yang disusun sales sendiri (bukan dari RFQ pelanggan): header
     * customer + item dari katalog / manual (produk atau jasa). Status awal
     * "dalam review" (draf) sampai dikirim lewat price(..., send: true).
     */
    public function createManual(array $header, ?User $actor = null): Quotation
    {
        return Quotation::create([
            'rfq_number' => 'SQ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)), // SQ = sales quotation
            'public_token' => (string) Str::uuid(),
            'user_id' => $header['user_id'] ?? null,
            'status' => QuotationStatus::UnderReview,
            'contact_name' => $header['contact_name'],
            'contact_email' => $header['contact_email'] ?? '',
            'contact_phone' => $header['contact_phone'] ?? null,
            'company_name' => $header['company_name'] ?? null,
            'npwp' => $header['npwp'] ?? null,
            'project_name' => $header['project_name'] ?? null,
            'project_location' => $header['project_location'] ?? null,
            'technical_notes' => $header['technical_notes'] ?? null,
            'handled_by' => $actor?->id,
        ]);
    }

    /** Ubah data customer/proyek pada penawaran (dipakai sales dari halaman detail). */
    public function updateHeader(Quotation $quotation, array $header): Quotation
    {
        $header['contact_email'] = $header['contact_email'] ?? '';
        $quotation->update(array_intersect_key($header, array_flip([
            'user_id', 'contact_name', 'contact_email', 'contact_phone', 'company_name', 'npwp', 'project_name', 'project_location', 'technical_notes',
        ])));

        return $quotation->fresh();
    }

    /**
     * Admin menetapkan harga & menyusun baris. Setiap baris: id (null = baris baru),
     * name, note, quantity, product_id, product_variant_id, unit_price, discount,
     * is_taxable. Baris lama yang tidak ada di daftar dihapus. Total dihitung ulang
     * di server. $send = kirim ke pelanggan (nomor penawaran, status "dikirim",
     * snapshot revisi, notifikasi); false = simpan sebagai draf.
     *
     * @param  array<int,array<string,mixed>>  $pricedItems
     */
    public function price(Quotation $quotation, array $pricedItems, array $meta, ?User $actor = null, bool $send = true): Quotation
    {
        return DB::transaction(function () use ($quotation, $pricedItems, $meta, $actor, $send) {
            $subtotal = 0.0;
            $taxable = 0.0;
            $keep = [];

            foreach ($pricedItems as $priced) {
                $item = ! empty($priced['id']) ? $quotation->items()->findOrFail((int) $priced['id']) : $quotation->items()->make();
                $quantity = max(1, (int) ($priced['quantity'] ?? $item->quantity ?: 1));
                $unit = round((float) $priced['unit_price'], 2);
                $discount = round((float) ($priced['discount'] ?? 0), 2);
                $lineTotal = max(0, $unit * $quantity - $discount);
                $isTaxable = (bool) ($priced['is_taxable'] ?? true);

                $item->fill([
                    'name' => isset($priced['name']) && trim((string) $priced['name']) !== '' ? trim((string) $priced['name']) : $item->name,
                    'note' => array_key_exists('note', $priced) ? ($priced['note'] ?: null) : $item->note,
                    'product_id' => array_key_exists('product_id', $priced) ? ($priced['product_id'] ?: null) : $item->product_id,
                    'product_variant_id' => array_key_exists('product_variant_id', $priced) ? ($priced['product_variant_id'] ?: null) : $item->product_variant_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                    'is_taxable' => $isTaxable,
                ])->save();
                $keep[] = $item->id;

                $subtotal += $lineTotal;
                if ($isTaxable) {
                    $taxable += $lineTotal;
                }
            }
            $quotation->items()->whereNotIn('id', $keep)->delete();

            $headerDiscount = round((float) ($meta['discount'] ?? 0), 2);
            $shipping = round((float) ($meta['shipping_cost'] ?? 0), 2);
            $taxBase = max(0, $taxable - $headerDiscount);
            $taxAmount = ($meta['apply_tax'] ?? true) ? $this->tax->taxOnExclusive($taxBase) : 0.0;
            $grandTotal = round($subtotal - $headerDiscount + $shipping + $taxAmount, 2);

            $quotation->update([
                'quotation_number' => $quotation->quotation_number ?? ($send ? 'QUO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)) : null),
                'status' => $send ? QuotationStatus::QuoteSent : (in_array($quotation->status, [QuotationStatus::QuoteSent, QuotationStatus::Revised], true) ? QuotationStatus::Revised : QuotationStatus::UnderReview),
                'items_subtotal' => $subtotal,
                'discount' => $headerDiscount,
                'shipping_cost' => $shipping,
                'tax_amount' => $taxAmount,
                'grand_total' => max(0, $grandTotal),
                'payment_terms' => $meta['payment_terms'] ?? null,
                'valid_until' => $meta['valid_until'] ?? null,
                'admin_note' => $meta['admin_note'] ?? null,
                'handled_by' => $actor?->id ?? $quotation->handled_by,
            ]);

            if ($send) {
                $version = ($quotation->revisions()->max('version') ?? 0) + 1;
                $quotation->revisions()->create([
                    'version' => $version,
                    'snapshot' => $quotation->fresh('items')->toArray(),
                    'created_by' => $actor?->id,
                ]);

                $this->notifications->toUser(
                    $quotation->user,
                    'Penawaran dikirim',
                    "Penawaran {$quotation->quotation_number} telah dikirim. Total: ".rupiah($quotation->grand_total),
                    route('quotations.show', $quotation->public_token),
                    'quotation',
                );
            }

            return $quotation->fresh(['items', 'revisions']);
        });
    }

    /** Approved quotation -> a real order the customer can pay. */
    public function convertToOrder(Quotation $quotation, ?User $actor = null): Order
    {
        if ($quotation->converted_order_id) {
            return Order::findOrFail($quotation->converted_order_id);
        }

        return DB::transaction(function () use ($quotation, $actor) {
            $order = Order::create([
                'order_number' => 'RS-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'public_token' => (string) Str::uuid(),
                'user_id' => $quotation->user_id,
                'customer_name' => $quotation->contact_name,
                'customer_email' => $quotation->contact_email,
                'customer_phone' => $quotation->contact_phone,
                'status' => OrderStatus::AwaitingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'items_subtotal' => $quotation->items_subtotal,
                'coupon_discount' => $quotation->discount,
                'shipping_cost' => $quotation->shipping_cost,
                'tax_amount' => $quotation->tax_amount,
                'grand_total' => $quotation->grand_total,
                'shipping_cost_confirmed' => true,
                'shipping_service_name' => 'Sesuai penawaran',
                'payment_method' => 'manual_transfer',
                'customer_note' => 'Dibuat dari penawaran '.$quotation->quotation_number,
            ]);

            foreach ($quotation->items as $qItem) {
                $order->items()->create([
                    'product_id' => $qItem->product_id,
                    'product_variant_id' => $qItem->product_variant_id,
                    'sku' => $qItem->product?->sku ?? 'QUO',
                    'name' => $qItem->name,
                    'unit_price' => $qItem->unit_price,
                    'original_unit_price' => $qItem->unit_price,
                    'quantity' => $qItem->quantity,
                    'discount_amount' => $qItem->discount,
                    'line_total' => $qItem->line_total,
                    'is_taxable' => $qItem->is_taxable,
                ]);
            }

            $order->addresses()->create([
                'type' => 'shipping',
                'recipient_name' => $quotation->contact_name,
                'phone' => $quotation->contact_phone ?? '-',
                'company_name' => $quotation->company_name,
                'npwp' => $quotation->npwp,
                'province' => '-', 'city' => $quotation->project_location ?? '-',
                'address_line' => $quotation->project_location ?? 'Sesuai penawaran',
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::AwaitingPayment->value,
                'changed_by' => $actor?->id,
                'customer_note' => 'Pesanan dibuat dari penawaran.',
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => 'manual_transfer',
                'status' => PaymentStatus::Unpaid->value,
                'amount' => $order->grand_total,
                'expires_at' => now()->addDays(7),
            ]);

            $this->invoices->createForOrder($order);

            $quotation->update([
                'status' => QuotationStatus::Converted,
                'converted_order_id' => $order->id,
            ]);

            return $order;
        });
    }
}
