<?php

use App\Http\Middleware\CheckOrderOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Desde Laravel 11 no existe app/Http/Kernel.php: los alias de
        // middleware, que antes vivían en $middlewareAliases, se registran aquí.
        $middleware->alias([
            'order.owner' => CheckOrderOwner::class,
        ]);

        // Evita redirecciones a una ruta "fantasma" en peticiones no autenticadas
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Estos render() devuelven la respuesta ya construida, así que Laravel
        // no le añade el bloque de depuración (exception, file, line, trace)
        // ni siquiera con APP_DEBUG=true.

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No autenticado. Incluye un token válido en la cabecera Authorization.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        });

        // Cubre también ModelNotFoundException: Laravel la convierte en
        // NotFoundHttpException, cuyo mensaje por defecto delata el modelo
        // y el id consultados.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'El recurso solicitado no existe.',
                ], Response::HTTP_NOT_FOUND);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'No tienes permiso para realizar esta acción.',
                ], Response::HTTP_FORBIDDEN);
            }
        });
    })->create();
