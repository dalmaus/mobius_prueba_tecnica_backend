<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_creates_an_order_with_its_items(): void
    {
        Sanctum::actingAs($this->user);

        $keyboard = Product::factory()->create(['price' => 10.50, 'stock' => 10]);
        $mouse = Product::factory()->create(['price' => 25.00, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $keyboard->id, 'quantity' => 3],
                ['product_id' => $mouse->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.total', '81.50')   // 3*10.50 + 2*25.00
            ->assertJsonCount(2, 'data.items')
            ->assertJsonStructure([
                'data' => [
                    'id', 'total', 'status', 'created_at',
                    'items' => [['id', 'quantity', 'unit_price', 'subtotal', 'product' => ['id', 'name', 'price', 'stock']]],
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => OrderStatus::Pending->value,
            'total' => 81.50,
        ]);
    }

    public function test_observer_copies_catalogue_price_and_computes_subtotal(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['price' => 19.99, 'stock' => 10]);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertCreated();

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 19.99,
            'subtotal' => 79.96,
        ]);
    }

    public function test_ignores_a_price_sent_by_the_client(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['price' => 100.00, 'stock' => 10]);

        $this->postJson('/api/orders', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 0.01,   // intento de manipular el precio
                'subtotal' => 0.01,
            ]],
        ])->assertCreated()->assertJsonPath('data.total', '100.00');

        $this->assertDatabaseHas('order_items', ['unit_price' => 100.00]);
    }

    public function test_listener_discounts_product_stock(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_rejects_an_order_without_enough_stock(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['stock' => 2]);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        // Ni pedido a medias ni stock tocado.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_sums_quantities_of_a_repeated_product(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['stock' => 3]);

        // Dos líneas de 2 unidades: por separado caben, juntas no.
        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_validates_the_request_payload(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/api/orders', [])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson('/api/orders', ['items' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson('/api/orders', ['items' => [['product_id' => 9999, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 0]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_requires_authentication(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_assigns_the_order_to_the_authenticated_user(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['stock' => 10]);

        // Aunque el cliente intente colar un user_id ajeno.
        $this->postJson('/api/orders', [
            'user_id' => $otherUser->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame($this->user->id, Order::sole()->user_id);
    }
}
