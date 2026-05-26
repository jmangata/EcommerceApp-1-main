<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;


class CheckoutController extends Controller
{
    /**
     * Affiche la page de validation de commande
     */
    public function index()
    {
        $cart = auth()->user()->cart;

        if (!$cart || $cart->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Votre panier est vide.');
        }

        $cart->load(['items.product.category']);

        return view('checkout.index', compact('cart'));
    }

    /**
     * Traite la commande et redirige vers Stripe Checkout
     */
    public function process(Request $request)
    {
        $cart = auth()->user()->cart;

        if (!$cart || $cart->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Votre panier est vide.');
        }

        $validated = $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_email' => 'required|email|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_postal_code' => 'required|string|max:10',
            'shipping_city' => 'required|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            foreach ($cart->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    throw new \Exception("Stock insuffisant pour {$item->product->name}");
                }
            }

            $order = Order::create([
                'user_id' => auth()->id(),
                'status' => OrderStatus::PENDING,
                'payment_status' => 'unpaid',
                'subtotal' => $cart->subtotal,
                'tax' => $cart->tax,
                'shipping' => $cart->shipping,
                'total' => $cart->total,
                'shipping_name' => $validated['shipping_name'],
                'shipping_email' => $validated['shipping_email'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_postal_code' => $validated['shipping_postal_code'],
                'shipping_city' => $validated['shipping_city'],
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);

                $item->product->decrement('stock_quantity', $item->quantity);
            }

            DB::commit();

            Stripe::setApiKey(config('services.stripe.secret'));

            $lineItems = [];
            foreach ($order->items as $orderItem) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $orderItem->product_name,
                        ],
                        'unit_amount' => (int) ($orderItem->price * 100),
                    ],
                    'quantity' => $orderItem->quantity,
                ];
            }

            $session = Session::create([
                'mode' => 'payment',
                'customer_email' => $validated['shipping_email'],
                'line_items' => $lineItems,
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                ],
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
            ]);

            $order->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            return redirect($session->url);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout error', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return back()
                ->withInput()
                ->with('error', 'Une erreur est survenue lors du traitement de votre commande. Veuillez réessayer.');
        }
    }

    /**
     * Gère le retour après paiement réussi.
     * Vérifie le statut du paiement auprès de Stripe avant de confirmer.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('cart.index')
                ->with('error', 'Session de paiement invalide.');
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = Session::retrieve($sessionId);
        } catch (\Exception $e) {
            Log::error('Stripe session retrieval failed on success callback', ['session_id' => $sessionId]);
            return redirect()->route('cart.index')
                ->with('error', 'Impossible de vérifier le paiement. Contactez le support.');
        }

        $order = Order::where('stripe_checkout_session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return redirect()->route('cart.index')
                ->with('error', 'Commande introuvable.');
        }

        if ($session->payment_status === 'paid' && $order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent,
                'paid_at' => now(),
            ]);

            $cart = auth()->user()->cart;
            if ($cart) {
                $cart->items()->delete();
            }
        }

        return view('checkout.success', compact('order'));
    }

    /**
     * Gère l'annulation du paiement.
     * Vérifie l'appartenance de la commande à l'utilisateur connecté.
     */
    public function cancel(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('cart.index')
                ->with('error', 'Session de paiement invalide.');
        }

        $order = Order::where('stripe_checkout_session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return redirect()->route('cart.index')
                ->with('error', 'Commande introuvable.');
        }

        if ($order->payment_status !== 'paid') {
            $order->update(['status' => OrderStatus::CANCELLED]);

            foreach ($order->items as $item) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }

        return redirect()->route('cart.index')
            ->with('error', 'Paiement annulé. Votre commande a été annulée. Vous pouvez recommencer.');
    }
}
