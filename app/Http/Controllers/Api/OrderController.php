<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Crea un pedido con sus líneas.
     *
     *  Todo ocurre dentro de una transacción: el observer de OrderItem fija
     *  unit_price y recalcula orders.total, y el listener de OrderCreated
     *  descuenta el stock. Si el stock no alcanza, el listener lanza
     *  excepción y la transacción revierte el pedido completo.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $order = $request->user()->orders()->create([
                'status' => OrderStatus::Pending,
            ]);

            foreach ($request->validated('items') as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            OrderCreated::dispatch($order->refresh());

            return $order;
        });

        // ->response() en lugar de response()->json() para conservar el
        // envoltorio "data" que aplican el resto de resources.
        return (new OrderResource($order->load('orderItems.product')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
