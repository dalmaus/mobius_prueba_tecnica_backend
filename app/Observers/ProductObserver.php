<?php

namespace App\Observers;

use App\Models\Product;

/**
 * Invalida el catálogo cacheado cuando cambia un producto por la vía normal
 * de Eloquent. Las escrituras atómicas con increment()/decrement() no pasan
 * por aquí y llaman a Product::forgetCachedIndex() por su cuenta.
 */
class ProductObserver
{
    public function saved(Product $product): void
    {
        Product::forgetCachedIndex();
    }

    public function deleted(Product $product): void
    {
        Product::forgetCachedIndex();
    }
}
