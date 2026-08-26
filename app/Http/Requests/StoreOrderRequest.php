<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * La ruta ya exige auth:sanctum; cualquier usuario autenticado
     * puede crear un pedido para sí mismo.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'El pedido debe incluir al menos una línea.',
            'items.min' => 'El pedido debe incluir al menos una línea.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.quantity.min' => 'La cantidad debe ser de al menos 1 unidad.',
        ];
    }

    /**
     * Comprobación de stock previa al guardado, tal y como pide el enunciado.
     * El listener vuelve a comprobarlo de forma atómica al descontar, porque
     * entre esta validación y el descuento puede colarse otro pedido.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $products = Product::findMany($this->quantitiesByProduct()->keys());

                foreach ($this->quantitiesByProduct() as $productId => $quantity) {
                    $product = $products->find($productId);

                    if ($product && $product->stock < $quantity) {
                        $validator->errors()->add(
                            'items',
                            "No hay stock suficiente de «{$product->name}»: quedan {$product->stock} unidades y se han pedido {$quantity}."
                        );
                    }
                }
            },
        ];
    }

    /**
     * Agrupa las cantidades por producto: un mismo producto puede aparecer
     * en varias líneas y el stock hay que comprobarlo sobre el total.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function quantitiesByProduct(): \Illuminate\Support\Collection
    {
        return collect($this->input('items', []))
            ->groupBy('product_id')
            ->map(fn ($lines) => collect($lines)->sum('quantity'));
    }
}
