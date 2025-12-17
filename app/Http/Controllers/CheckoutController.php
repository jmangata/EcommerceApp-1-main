<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
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
                'status' => 'PENDING',
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
            $cart->clear();

            DB::commit();

            return redirect()->route('checkout.success', $order)
                ->with('success', 'Commande passée avec succès !');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de la commande : ' . $e->getMessage());
        }
    }

    /**
     * Page de confirmation de commande
     */
    public function success(Order $order)
    {
        // Vérifie que la commande appartient à l'utilisateur connecté
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['items.product', 'user']);

        return view('checkout.success', compact('order'));
    }
}