<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;


class CheckoutController extends Controller
{
    // tester une commande
   public function teststripe(Request $request)  {
        

      $stripePriceId = "price_1SfjLLIm0fn9gxVOewEIs2tz" ;

      $quantity = 1;
 
        return $request->user()->checkout([$stripePriceId => $quantity], [
            'success_url' => route('checkout.success'),
            'cancel_url' => route('checkout.cancel'),
        ]);

    }

/**
     * Affiche la page de validation de commande
     */
    public function index()
    {
        $cart = auth()->user()->cart;

        // Redirige si panier vide
        if (!$cart || $cart->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Votre panier est vide.');
        }

        $cart->load(['items.product.category']);

        return view('checkout.index', compact('cart'));
    }

    /**
     * Traite la commande
     */
    public function process(Request $request)
    {
        
        $cart = auth()->user()->cart;

        // Vérifie que le panier n'est pas vide
        if (!$cart || $cart->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Votre panier est vide.');
        }

        // Validation des données de livraison
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

            // Vérifie le stock de tous les produits
            foreach ($cart->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    throw new \Exception("Stock insuffisant pour {$item->product->name}");
                }
            }

            // Crée la commande
            $order = Order::create([
                'user_id' => auth()->id(),
                'order_number' => 'CMD-' . strtoupper(uniqid()),
                'status' => OrderStatus::PENDING,
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

            // Crée les items de commande et décrémente le stock
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);

                // Décrémente le stock
                $item->product->decrement('stock_quantity', $item->quantity);
            }

            // Vide le panier
            // $cart->clear();

            DB::commit();


         Stripe::setApiKey(config('services.stripe.secret'));

$session = Session::create([
    'mode' => 'payment',

    'line_items' => [[
        'price_data' => [
            'currency' => 'eur',
            'product_data' => [
                'name' => 'Commande #' . $order->id,
                'description' => 'Paiement de votre commande',
            ],
            'unit_amount' => (int) ($order->total * 100),
        ],
        'quantity' => 1,
    ]],

    'metadata' => [
        'order_id' => $order->id,
    ],

    'success_url' => route('checkout.success', ['order' => $order->id]),
    'cancel_url'  => route('checkout.cancel', ['order' => $order->id]),
]);

$order->update([
    'stripe_checkout_session_id' => $session->id,
]);

return redirect($session->url);
           /* return redirect()->route('checkout.success', $order)
                ->with('success', 'Commande passée avec succès !');*/

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de la commande : ' . $e->getMessage());
        }
    }
    

    // commande réalisé
    public function success(Request $request)
    {
        $order = Order::findOrFail($request->query('order'));

        // Vide le panier après paiement réussi
        $cart = auth()->user()->cart;
        if ($cart) {
            $cart->items()->delete();
        }

        return view('checkout.success', compact('order'));
    }

    // commande annulé
    public function cancel(Request $request)
    {
        $order = Order::findOrFail($request->query('order'));

        $order->update(['status' => OrderStatus::CANCELLED]);

        return redirect()->route('cart.index')
            ->with('error', 'Paiement annulé. Votre commande a été annulée. Vous pouvez recommencer.');
    }
}