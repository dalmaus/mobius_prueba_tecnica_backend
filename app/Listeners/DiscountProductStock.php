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
            $hasStock = Product::decrementStock(
                $orderItem->product_id,
                $orderItem->quantity,
            );

            // false significa que otro pedido se llevó las últimas unidades
            // entre la validación del Form Request y este momento.
            if (! $hasStock) {
                throw new InsufficientStockException(
                    Product::findOrFail($orderItem->product_id),
                    $orderItem->quantity,
                );
            }
        }
    }
}
