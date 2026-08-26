<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(10, 200),
        ];
    }

    /**
     * Producto agotado, para probar la validación de stock.
     */
    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}
