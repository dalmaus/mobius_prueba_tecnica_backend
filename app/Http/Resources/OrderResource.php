<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'status' => $this->status,
            'created_at' => $this->created_at,
            // whenLoaded evita el N+1: si el listado no cargó los items,
            // la clave sencillamente no aparece en la respuesta.
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
