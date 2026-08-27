<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Exceptions\OrderNotCancellableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Lista los pedidos del usuario autenticado.
     *
     * No hace falta filtrar por propietario a mano: la consulta parte de la
     * relación del usuario, así que solo puede devolver pedidos suyos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with('orderItems.product')
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    /**
     * Detalle de un pedido con sus líneas y productos.
     * La propiedad la verifica el middleware order.owner.
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('orderItems.product'));
    }

    /**
     * Cancela un pedido pendiente y devuelve el stock reservado.
     * La propiedad la verifica el middleware order.owner.
     */
    public function cancel(Order $order): OrderResource
    {
        // Comprobación temprana para responder 422 sin abrir transacción en el
        // caso normal. La garantía real es el UPDATE condicional de más abajo.
        if (! $order->status->isCancellable()) {
            throw new OrderNotCancellableException($order);
        }

        $cancelled = DB::transaction(function () use ($order) {
            // La transición pending -> cancelled se hace con un UPDATE ... WHERE
            // status = 'pending': de dos cancelaciones simultáneas
            // solo una afecta a una fila, y solo esa devuelve el stock. Si
            // comprobásemos el estado en PHP, ambas pasarían el if y el stock se
            // restauraría dos veces.
            $affected = Order::query()
                ->whereKey($order->getKey())
                ->pending()
                ->update(['status' => OrderStatus::Cancelled]);

            if ($affected === 0) {
                return false;
            }

            // Contrapartida del listener DiscountProductStock: si el pedido
            // deja de existir el stock vuelve al catálogo.
            foreach ($order->orderItems as $orderItem) {
                Product::restoreStock($orderItem->product_id, $orderItem->quantity);
            }

            return true;
        });

        // Perdimos la carrera: otra petición cambió el estado entre el if y el
        // UPDATE. El pedido ya no es cancelable, así que la respuesta es la misma.
        if (! $cancelled) {
            throw new OrderNotCancellableException($order->refresh());
        }

        return new OrderResource($order->refresh()->load('orderItems.product'));
    }

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
