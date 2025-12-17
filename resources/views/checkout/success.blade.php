@extends('layouts.boutique')

@section('title', 'Commande confirmée')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Message de succès -->
    <div class="bg-green-50 border-2 border-green-500 rounded-lg p-8 mb-8 text-center">
        <svg class="mx-auto h-16 w-16 text-green-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h1 class="text-3xl font-bold text-green-800 mb-2">🎉 Commande confirmée !</h1>
        <p class="text-green-700 text-lg">
            Merci pour votre commande. Un email de confirmation vous a été envoyé.
        </p>
    </div>

    <!-- Détails de la commande -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-2xl font-bold mb-6">Détails de votre commande</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Numéro de commande -->
            <div>
                <p class="text-sm text-gray-500">Numéro de commande</p>
                <p class="font-mono text-lg font-bold">{{ $order->order_number }}</p>
            </div>

            <!-- Date -->
            <div>
                <p class="text-sm text-gray-500">Date</p>
                <p class="font-semibold">{{ $order->created_at->format('d/m/Y à H:i') }}</p>
            </div>

            <!-- Statut -->
            <div>
                <p class="text-sm text-gray-500">Statut</p>
                <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-semibold">
                    En attente de traitement
                </span>
            </div>

            <!-- Total -->
            <div>
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-blue-600">{{ $order->formatted_total }}</p>
            </div>
        </div>

        <!-- Adresse de livraison -->
        <div class="border-t pt-6 mb-8">
            <h3 class="font-bold text-lg mb-3">📦 Adresse de livraison</h3>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="font-semibold">{{ $order->shipping_name }}</p>
                <p class="text-gray-700">{{ $order->shipping_address }}</p>
                <p class="text-gray-700">{{ $order->shipping_postal_code }} {{ $order->shipping_city }}</p>
                <p class="text-gray-700 mt-2">📧 {{ $order->shipping_email }}</p>
                <p class="text-gray-700">📞 {{ $order->shipping_phone }}</p>
            </div>
        </div>

        <!-- Articles commandés -->
        <div class="border-t pt-6">
            <h3 class="font-bold text-lg mb-4">Articles commandés</h3>
            <div class="space-y-4">
                @foreach($order->items as $item)
                    <div class="flex gap-4">
                        <img src="{{ $item->product->image_url }}" 
                             alt="{{ $item->product_name }}"
                             class="w-20 h-20 object-cover rounded">
                        <div class="flex-grow">
                            <p class="font-semibold">{{ $item->product_name }}</p>
                            <p class="text-sm text-gray-500">Quantité : {{ $item->quantity }}</p>
                            <p class="text-sm">Prix unitaire : {{ $item->formatted_price }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">{{ $item->formatted_subtotal }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('products.index') }}" 
           class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg text-center transition">
            Continuer mes achats
        </a>
        <a href="{{ url('/customer') }}" 
           class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-3 rounded-lg text-center transition">
            Voir mes commandes
        </a>
    </div>

</div>
@endsection 