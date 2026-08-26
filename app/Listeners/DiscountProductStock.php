<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;

/**
 * Deliberadamente NO implementa ShouldQueue: se ejecuta de forma síncrona
 * dentro de la transacción que abre el controlador, de modo que si lanza
 * excepción el pedido entero se revierte.
 */
class DiscountProductStock
{
    public function handle(OrderCreated $event): void
    {
        foreach ($event->order->orderItems as $orderItem) {
            // UPDATE condicional: la comprobación de stock y el descuento
            // ocurren en la misma sentencia, así que dos pedidos simultáneos
            // no pueden dejar el stock en negativo.
            $updated = Product::whereKey($orderItem->product_id)
                ->where('stock', '>=', $orderItem->quantity)
                ->decrement('stock', $orderItem->quantity);

            if ($updated === 0) {
                throw new InsufficientStockException(
                    Product::findOrFail($orderItem->product_id),
                    $orderItem->quantity,
                );
            }
        }
    }
}
