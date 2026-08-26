<?php

namespace App\Models;

use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin IdeHelperProduct
 */
#[Fillable(['name', 'price', 'stock'])]
#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /** Clave y duración del catálogo cacheado que sirve ProductController. */
    public const CACHE_KEY = 'products.index';

    public const CACHE_TTL_SECONDS = 60*5; 

    /**
     * Descuenta stock de forma atómica.
     *
     * La comprobación y el descuento van en la misma sentencia, de modo que
     * dos pedidos simultáneos no pueden dejar el stock en negativo.
     *
     * @return bool false si no había stock suficiente
     */
    public static function decrementStock(int $productId, int $quantity): bool
    {
        $updated = static::whereKey($productId)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($updated === 0) {
            return false;
        }

        static::forgetCachedIndex();

        return true;
    }

    /**
     * Devuelve al catálogo el stock reservado por un pedido cancelado.
     */
    public static function restoreStock(int $productId, int $quantity): void
    {
        static::whereKey($productId)->increment('stock', $quantity);

        static::forgetCachedIndex();
    }

    /**
     * Invalida el catálogo cacheado que sirve ProductController.
     *
     * ProductObserver la llama en los eventos de Eloquent. Los métodos de
     * stock de arriba la llaman por su cuenta porque increment()/decrement()
     * escriben por el query builder y no disparan eventos de modelo.
     */
    public static function forgetCachedIndex(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
