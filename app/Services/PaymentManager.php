<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\DemoGateway;
use App\Services\Payment\GatewayResult;
use App\Services\Payment\ManualTransferGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Registry of payment gateways + the hardened webhook entry point.
 *
 * Webhook safety (spec §18/§30): signature is verified before anything is trusted;
 * a per-event idempotency key rejects replays and double-processing; every hit is
 * logged; and the settled amount comes from OUR order, not the payload.
 */
class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function __construct(private readonly OrderService $orders)
    {
        $this->register(app(ManualTransferGateway::class));
        $this->register(new DemoGateway());
    }

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    public function gateway(string $code): ?PaymentGateway
    {
        return $this->gateways[$code] ?? null;
    }

    /** @return PaymentGateway[] */
    public function available(): array
    {
        return array_values($this->gateways);
    }

    /**
     * Process an incoming webhook.
     *
     * @return array{status: string, message?: string}
     */
    public function handleWebhook(string $providerCode, array $payload, string $rawBody, string $signature, ?string $ip = null): array
    {
        $gateway = $this->gateway($providerCode);
        if (! $gateway) {
            return ['status' => 'unknown_provider'];
        }

        $signatureValid = $gateway->verifySignature($rawBody, $signature);
        $result = $gateway->parseWebhook($payload);
        $idempotencyKey = $providerCode.':'.($result->eventId ?? sha1($rawBody));

        // Replay / duplicate protection.
        $existing = PaymentWebhookLog::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->processed) {
            return ['status' => 'duplicate'];
        }

        $log = $existing ?? PaymentWebhookLog::create([
            'provider' => $providerCode,
            'event' => $payload['status'] ?? null,
            'external_id' => $result->externalId,
            'idempotency_key' => $idempotencyKey,
            'signature_valid' => $signatureValid,
            'processed' => false,
            'payload' => $rawBody,
            'ip_address' => $ip,
        ]);

        if (! $signatureValid) {
            return ['status' => 'invalid_signature'];
        }

        DB::transaction(function () use ($result, $log) {
            $payment = Payment::where('external_id', $result->externalId)->latest()->first();

            if ($payment && $result->status === PaymentStatus::Paid && $payment->status !== PaymentStatus::Paid->value) {
                // Amount is intentionally NOT read from the payload.
                $this->orders->markPaid($payment->order);
            } elseif ($payment && in_array($result->status, [PaymentStatus::Expired, PaymentStatus::Failed], true)) {
                $payment->update(['status' => $result->status->value]);
            }

            $log->update(['processed' => true]);
        });

        return ['status' => 'processed'];
    }

    /** Initialise the charge for a freshly created payment. */
    public function startCharge(Payment $payment): GatewayResult
    {
        $gateway = $this->gateway($payment->method) ?? app(ManualTransferGateway::class);
        $result = $gateway->createCharge($payment);

        $payment->update([
            'provider' => $gateway->code(),
            'status' => $result->status->value,
            'reference' => $result->reference,
            'external_id' => $result->externalId,
            'meta' => $result->meta,
        ]);

        return $result;
    }
}
