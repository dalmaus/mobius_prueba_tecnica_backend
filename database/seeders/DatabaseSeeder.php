<?php

namespace Database\Seeders;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ojo: este seeder NO usa el trait WithoutModelEvents. Ese trait silencia
 * los eventos de Eloquent, y con él OrderItemObserver no se ejecutaría:
 * los pedidos quedarían con unit_price a null y total a 0.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductSeeder::class);

        // Usuario de pruebas con credenciales conocidas, documentadas en el README.
        $demo = User::factory()->create([
            'name' => 'Usuario Demo',
            'email' => 'demo@mobius.test',
            'password' => 'password',
        ]);

        // Segundo usuario, para comprobar que CheckOrderOwner devuelve 403.
        User::factory()->create([
            'name' => 'Sergio',
            'email' => 'sd@mobius.test',
            'password' => 'password',
        ]);

        $products = Product::where('stock', '>', 5)->get();

        $this->createOrder($demo, $products->random(2), 'pending');
        $this->createOrder($demo, $products->random(3), 'completed');
        $this->createOrder($demo, $products->random(1), 'cancelled');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function createOrder(User $user, $products, string $status): void
    {
        $order = Order::factory()->{$status}()->create(['user_id' => $user->id]);

        foreach ($products as $product) {
            $order->orderItems()->create([
                'product_id' => $product->id,
                'quantity' => random_int(1, 3),
            ]);
        }

        // Un pedido cancelado no retiene stock, así que solo se descuenta en
        // los otros dos. Se reutiliza el listener real en lugar de duplicar
        // aquí la lógica de descuento.
        if ($status !== 'cancelled') {
            OrderCreated::dispatch($order->refresh());
        }
    }
}
