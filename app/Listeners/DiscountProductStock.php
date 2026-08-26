<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;


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
