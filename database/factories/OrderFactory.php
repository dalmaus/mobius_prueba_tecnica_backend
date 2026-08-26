<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * total no se define aquí: lo calcula el observer a partir de las líneas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }

    public function completed(): static
    {
        return $this->state(['status' => OrderStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }
}
