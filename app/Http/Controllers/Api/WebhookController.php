<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Jobs\FireAwinConversionJob;
use Fynla\Core\Models\Payment;
use Fynla\Core\Models\SubscriptionPlan;
use App\Services\Payment\RevolutService;
use App\Services\Payment\SubscriptionRenewalService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService,
        private readonly SubscriptionRenewalService $renewalService
    ) {}

    /**
     * Handle Revolut webhook events.
     *
     * POST /api/webhooks/revolut
     *
     * Headers: Revolut-Signature, Revolut-Request-Timestamp
     * Body: { event, order_id }
     *
     * Responds 200 to acknowledge. Revolut retries 3x with 10-min delay on failure.
     */
    public function handleRevolut(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signatureHeader = $request->header('Revolut-Signature', '');
        $timestampHeader = $request->header('Revolut-Request-Timestamp', '');

        // Verify HMAC signature (v1.{timestamp}.{payload})
        if (! $this->revolutService->verifyWebhookSignature($rawPayload, $signatureHeader, $timestampHeader)) {
            Log::warning('Revolut webhook signature verification failed');

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $event = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $merchantRef = $payload['merchant_order_ext_ref'] ?? null;

        Log::info('Revolut webhook received', [
            'event' => $event,
            'order_id' => $orderId,
            'merchant_order_ext_ref' => $merchantRef,
        ]);

        match ($event) {
            'ORDER_COMPLETED', 'ORDER_AUTHORISED' => $orderId ? $this->handleOrderCompleted($orderId, $merchantRef) : null,
            'SUBSCRIPTION_INITIATED' => $this->handleSubscriptionInitiated($payload),
            'SUBSCRIPTION_OVERDUE' => $this->handleSubscriptionOverdue($payload),
            'SUBSCRIPTION_CANCELLED' => $this->handleSubscriptionCancelled($payload),
            'SUBSCRIPTION_FINISHED' => $this->handleSubscriptionFinished($payload),
            default => Log::warning('Revolut webhook: unhandled event', ['event' => $event]),
        };

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    private function handleOrderCompleted(string $orderId, ?string $merchantRef): void
    {
        try {
            DB::transaction(function () use ($orderId, $merchantRef) {
                $payment = Payment::where('revolut_order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('Revolut webhook: payment not found', [
                        'order_id' => $orderId,
                        'merchant_ref' => $merchantRef,
                    ]);

                    return;
                }

                // Cross-reference check — fail closed on mismatch.
                // Slice 2 H-3: confused-deputy protection requires the webhook
                // payload's merchant_ref (when present) to point at the same
                // Payment row that matched revolut_order_id. Initial purchases
                // send no merchant_ref (null is fine); upgrades send
                // "upgrade_{$payment->id}".
                $expectedRefs = [
                    "payment_{$payment->id}",
                    "upgrade_{$payment->id}",
                ];
                if ($merchantRef && ! in_array($merchantRef, $expectedRefs, true)) {
                    Log::warning('Revolut webhook: merchant_ref mismatch — aborting', [
                        'order_id' => $orderId,
                        'expected' => $expectedRefs,
                        'received' => $merchantRef,
                    ]);

                    return;
                }

                // Idempotent: skip if already completed
                if ($payment->status === 'completed') {
                    Log::info('Revolut webhook: payment already completed', ['order_id' => $orderId]);

                    return;
                }

                // Verify with Revolut API
                $revolutOrder = $this->revolutService->getOrder($orderId);
                $captureMode = $revolutOrder['capture_mode'] ?? null;

                // Slice 2 M-6: fail-loud on missing capture_mode rather than
                // silently defaulting to 'automatic'.
                if ($captureMode === null) {
                    Log::warning('Revolut webhook: order missing capture_mode — aborting', [
                        'order_id' => $orderId,
                        'state' => $revolutOrder['state'] ?? null,
                    ]);

                    return;
                }

                $acceptableStates = $captureMode === 'manual'
                    ? ['completed', 'authorised']
                    : ['completed'];

                if (! in_array($revolutOrder['state'], $acceptableStates)) {
                    Log::warning('Revolut webhook: order not in acceptable state', [
                        'order_id' => $orderId,
                        'state' => $revolutOrder['state'],
                        'capture_mode' => $captureMode,
                    ]);

                    return;
                }

                // Read plan and billing cycle from the Payment record (source of truth)
                $planSlug = $payment->plan_slug;
                $billingCycle = $payment->billing_cycle;

                $periodEnd = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();

                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);

                // Activate payment
                $payment->update([
                    'status' => 'completed',
                    'revolut_payment_data' => $revolutOrder,
                ]);

                // Update subscription from payment data
                $subscription = $payment->subscription;
                $subscription->update([
                    'status' => 'active',
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                    'amount' => $subscriptionPlan ? $subscriptionPlan->getPriceForCycle($billingCycle) : $payment->amount,
                    'auto_renew' => true,
                    'payment_method_saved' => true,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                    'revolut_order_id' => $orderId,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ]);

                $user = $payment->user;
                $user->update([
                    'plan' => $planSlug,
                    'trial_ends_at' => null,
                ]);

                // Confirmation email is sent from confirmPayment() after invoice
                // generation so the PDF can be attached. Not sent here because
                // the invoice doesn't exist yet at webhook time.

                Log::info('Revolut webhook: subscription activated', [
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                ]);

                // Fire Awin conversion (idempotent — job short-circuits if
                // awin_fired_at is already set). Dispatched from both webhook
                // and confirmPayment paths; whichever arrives second is a
                // no-op. Admin accounts are excluded.
                if (config('awin.enabled') && ! $user->is_admin) {
                    FireAwinConversionJob::dispatch($payment->id);
                }
            });
        } catch (QueryException $e) {
            // Slice 2 H-1: a duplicate-key violation on payments.revolut_order_id
            // means a concurrent webhook already created/updated the row. Treat
            // as idempotent rather than retrying.
            if ($this->isDuplicateKeyError($e)) {
                Log::info('Revolut webhook: duplicate processing detected — treating as idempotent', [
                    'order_id' => $orderId,
                ]);

                return;
            }

            Log::error('Revolut webhook DB error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            // Slice 2 M-1: re-throw so Revolut sees non-2xx and retries.
            // Default Revolut retry policy: 3× with 10-min delay.
            Log::error('Revolut webhook processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry');
    }

    private function handleSubscriptionInitiated(array $payload): void
    {
        Log::info('Revolut subscription initiated', ['payload' => $payload]);

        // The subscription is now active — Revolut will handle recurring billing.
        // The initial payment is handled via ORDER_COMPLETED for the setup order.
        // Future renewal payments also come via ORDER_COMPLETED for each cycle's order.
    }

    private function handleSubscriptionOverdue(array $payload): void
    {
        try {
            $this->renewalService->handleSubscriptionOverdue($payload);
        } catch (\Throwable $e) {
            Log::error('Failed to handle subscription overdue webhook', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        try {
            $this->renewalService->handleSubscriptionCancelled($payload);
        } catch (\Throwable $e) {
            Log::error('Failed to handle subscription cancelled webhook', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function handleSubscriptionFinished(array $payload): void
    {
        try {
            $this->renewalService->handleSubscriptionFinished($payload);
        } catch (\Throwable $e) {
            Log::error('Failed to handle subscription finished webhook', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
