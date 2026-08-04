<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function track(Order $order): View
    {
        $order->load(['items', 'shippingAddress', 'statusHistories.changedBy', 'payments', 'invoice', 'shipments', 'documentations']);

        return view('storefront.order-track', ['order' => $order]);
    }

    public function pay(Order $order): View
    {
        $order->load(['items', 'payments', 'invoice']);
        $payment = $order->payments()->latest()->first();

        return view('storefront.order-pay', [
            'order' => $order,
            'payment' => $payment,
        ]);
    }

    public function invoice(Invoice $invoice): View
    {
        $invoice->load(['order.items', 'order.shippingAddress']);

        return view('storefront.invoice', ['invoice' => $invoice]);
    }

    public function invoicePdf(Invoice $invoice): Response
    {
        $invoice->load(['order.items', 'order.shippingAddress']);

        $pdf = Pdf::loadView('storefront.invoice-pdf', ['invoice' => $invoice])->setPaper('a4');

        return $pdf->download('Invoice-'.$invoice->invoice_number.'.pdf');
    }
}
