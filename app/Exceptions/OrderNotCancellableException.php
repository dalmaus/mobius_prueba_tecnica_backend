<?php

namespace App\Exceptions;

use App\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderNotCancellableException extends Exception
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct(
            "No se puede cancelar un pedido en estado «{$order->status->value}»: solo los pedidos pendientes admiten cancelación."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'status' => [$this->getMessage()],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
