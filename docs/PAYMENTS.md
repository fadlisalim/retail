# Payments: adapters & webhook security

Payments use an **adapter architecture** so a new gateway is one class + one
registration, with checkout and orders untouched.

## Contract

Every method implements `App\Services\Payment\PaymentGateway`:

```php
interface PaymentGateway {
    public function code(): string;            // e.g. 'va_demo'
    public function label(): string;           // shown at checkout
    public function isAsync(): bool;           // settles via webhook?
    public function createCharge(Payment $p): GatewayResult;   // VA number / instructions
    public function verifySignature(string $rawBody, string $signature): bool;
    public function parseWebhook(array $payload): GatewayResult;
}
```

`App\Services\PaymentManager` is the registry + hardened webhook entry point.
Two adapters ship:

- **`ManualTransferGateway`** — bank transfer; no webhook. The customer uploads
  proof and an admin with `payment.manage` verifies it, calling
  `OrderService::markPaid`.
- **`DemoGateway` (`va_demo`)** — a reference asynchronous (virtual-account) gateway
  demonstrating the exact security model a real integration must follow.

## Webhook security (spec §18 / §30)

Route: `POST /webhook/pembayaran/{provider}` → `PaymentWebhookController` →
`PaymentManager::handleWebhook`. It is **CSRF-exempt** (gateways can't send a token)
but protected by:

1. **Signature verification** — `verifySignature($rawBody, $signature)` uses
   `hash_hmac('sha256', rawBody, secret)` + `hash_equals` (constant-time). The
   secret comes from `.env` (`DEMO_GATEWAY_SECRET`), **never** source. An invalid
   signature returns `401` and changes nothing.
2. **Replay / duplicate protection** — a per-event idempotency key
   (`provider:event_id`) is stored in `payment_webhook_logs.idempotency_key`
   (unique). A repeat delivery returns `duplicate` and is not re-processed.
3. **Idempotent processing** — inside a DB transaction; the order is marked paid
   only once.
4. **Amount is never trusted from the payload** — settlement uses the amount on our
   own `orders` record, preventing amount manipulation.
5. **Full logging** — every hit (valid or not) is recorded with IP + raw payload.

On a verified `PAID` event, `OrderService::markPaid` runs: it **commits the stock
reservation into a real sale** (ledger movement), sets `payment_status = Paid`,
advances the order to *Pembayaran Diverifikasi*, and notifies the customer.

## Adding a real gateway (Midtrans / Xendit / aggregator)

1. Create `app/Services/Payment/YourGateway.php` implementing `PaymentGateway`.
   - `createCharge()`: call the gateway's charge API, return the VA/QR/redirect in
     `GatewayResult` (store `external_id` so the webhook can find the payment).
   - `verifySignature()`: implement the provider's signature scheme.
   - `parseWebhook()`: map their statuses to `PaymentStatus`.
2. Register it in `PaymentManager::__construct()` (or bind via the container).
3. Add its keys to `.env` + `config/services.php` (never hardcode).
4. Point the provider's webhook at `/webhook/pembayaran/your-code`.

No changes to `CheckoutService`, `OrderService`, controllers, or views are needed —
they depend only on the `PaymentGateway` interface and `GatewayResult`.

## Payment statuses

`Belum Dibayar · Menunggu Verifikasi · DP Dibayar · Lunas · Gagal · Kedaluwarsa ·
Dikembalikan Sebagian · Dikembalikan Penuh` (`App\Enums\PaymentStatus`).
Down-payment and term/tempo (approved customers only) are supported by the schema.
