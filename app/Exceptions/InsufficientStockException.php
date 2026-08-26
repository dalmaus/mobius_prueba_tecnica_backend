<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requested,
    ) {
        parent::__construct(
            "No hay stock suficiente de «{$product->name}»: quedan {$product->stock} unidades y se han pedido {$requested}."
        );
    }

    /**
     * Convierte la excepción en respuesta en respuesta no hace falta captura en controlador
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'items' => [$this->getMessage()],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
