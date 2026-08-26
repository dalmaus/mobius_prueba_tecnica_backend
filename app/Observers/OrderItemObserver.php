<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\Product;

class OrderItemObserver
{
    /**
     * unit_price se copia del precio actual del producto y subtotal se deriva
     * de la cantidad. Al hacerlo aquí, ningún punto de la aplicación puede
     * crear una línea con un precio que no sea el del catálogo.
     */
    public function creating(OrderItem $orderItem): void
    {
        $orderItem->unit_price ??= Product::findOrFail($orderItem->product_id)->price;
        $orderItem->subtotal = $orderItem->quantity * $orderItem->unit_price;
    }

    /**
     * Si cambia la cantidad hay que recalcular el subtotal de la línea.
     */
    public function updating(OrderItem $orderItem): void
    {
        if ($orderItem->isDirty(['quantity', 'unit_price'])) {
            $orderItem->subtotal = $orderItem->quantity * $orderItem->unit_price;
        }
    }

    public function created(OrderItem $orderItem): void
    {
        $this->recalculateOrderTotal($orderItem);
    }

    public function updated(OrderItem $orderItem): void
    {
        $this->recalculateOrderTotal($orderItem);
    }

    public function deleted(OrderItem $orderItem): void
    {
        $this->recalculateOrderTotal($orderItem);
    }

    /**
     * Suma los subtotales en base de datos en lugar de en memoria, para no
     * depender de qué relaciones estén cargadas en este momento.
     */
    private function recalculateOrderTotal(OrderItem $orderItem): void
    {
        $order = $orderItem->order()->first();

        if (! $order) {
            return;
        }

        $order->forceFill([
            'total' => $order->orderItems()->sum('subtotal'),
        ])->saveQuietly();
    }
}
