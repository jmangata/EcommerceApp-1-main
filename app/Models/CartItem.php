<?php

namespace App\Models;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
    ];

    /**
     * Relation : un item appartient à un panier
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Relation : un item référence un produit
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Sous-total de cet item (quantité × prix)
     */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price;
    }

    /**
     * Sous-total formaté
     */
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, ',', ' ') . ' €';
    }

    /**
     * Prix formaté
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2, ',', ' ') . ' €';
    }

    /**
     * Incrémente la quantité
     */
    public function incrementQuantity()
    {
        $this->quantity++;
        $this->save();
    }

    /**
     * Décrémente la quantité (minimum 1)
     */
    public function decrementQuantity()
    {
        if ($this->quantity > 1) {
            $this->quantity--;
            $this->save();
        }
    }
}