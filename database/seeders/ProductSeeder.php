<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo fijo: nombres y precios reconocibles para probar la API
        // a mano sin tener que consultar antes qué se ha generado.
        $catalogue = [
            ['name' => 'Teclado mecánico', 'price' => 89.90, 'stock' => 25],
            ['name' => 'Ratón inalámbrico', 'price' => 24.50, 'stock' => 40],
            ['name' => 'Monitor 27"', 'price' => 249.00, 'stock' => 12],
            ['name' => 'Auriculares con cancelación', 'price' => 179.99, 'stock' => 8],
            ['name' => 'Webcam 1080p', 'price' => 59.95, 'stock' => 30],
            // Sin stock, para poder probar el 422 de la validación.
            ['name' => 'Silla ergonómica', 'price' => 329.00, 'stock' => 0],
        ];

        foreach ($catalogue as $product) {
            Product::create($product);
        }

        Product::factory(10)->create();
    }
}
