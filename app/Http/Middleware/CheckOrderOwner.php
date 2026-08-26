<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que el usuario autenticado solo pueda ver o cancelar sus
 * propios pedidos. Se aplica a las rutas de detalle y cancelación.
 */
class CheckOrderOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        // El grupo de middleware 'api' ya ha ejecutado SubstituteBindings,
        // así que aquí llega el modelo resuelto y no el id de la URL.
        if (! $order instanceof Order) {
            abort(Response::HTTP_NOT_FOUND, 'El pedido solicitado no existe.');
        }

        if ($order->user_id !== $request->user()?->id) {
            abort(Response::HTTP_FORBIDDEN, 'No tienes permiso para acceder a este pedido.');
        }

        return $next($request);
    }
}
