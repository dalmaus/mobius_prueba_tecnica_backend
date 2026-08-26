<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            throw new NotFoundHttpException('El pedido solicitado no existe.');
        }

        // Excepción específica en vez de abort(403): así el renderer de
        // bootstrap/app.php puede darle un formato uniforme.
        if ($order->user_id !== $request->user()?->id) {
            throw new AccessDeniedHttpException('No tienes permiso para acceder a este pedido.');
        }

        return $next($request);
    }
}
