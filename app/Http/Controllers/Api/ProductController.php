<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Catálogo de productos, cacheado 5 minutos.
     *
     * Se guardan arrays planos, no modelos: config/cache.php fija
     * serializable_classes en false, así que la caché no puede deserializar
     * objetos. Al rehidratarlos después, ProductResource sigue siendo el
     * único responsable del formato de salida.
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Cache::remember(
            Product::CACHE_KEY,
            Product::CACHE_TTL_SECONDS,
            fn (): array => Product::orderBy('name')->get()->toArray(),
        );

        return ProductResource::collection(Product::hydrate($products));
    }
}
