<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            Log::error('Stripe webhook secret is not configured');
            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload');
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed');
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'checkout.session.expired':
                $this->handleCheckoutSessionExpired($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event type: ' . $event->type);
        }

        return response('OK', 200);
    }

    private function handleCheckoutSessionCompleted($session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (!$order) {
            Log::warning('Webhook: order not found for session', ['session_id' => $session->id]);
            return;
        }

        if ($session->payment_status === 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent,
                'payment_method' => $session->payment_method_types[0] ?? null,
                'paid_at' => now(),
            ]);
        }
    }

    private function handleCheckoutSessionExpired($session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (!$order) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            $order->update(['status' => 'cancelled']);

            foreach ($order->items as $item) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }
    }
}
