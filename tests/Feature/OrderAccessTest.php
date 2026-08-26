<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre el middleware CheckOrderOwner y las reglas de cancelación.
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $juan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = User::factory()->create();
        $this->juan = User::factory()->create();
    }

    private function orderFor(User $user, OrderStatus $status = OrderStatus::Pending, int $quantity = 2): Order
    {
        $product = Product::factory()->create(['price' => 10.00, 'stock' => 50]);

        return Order::factory()
            ->state(['user_id' => $user->id, 'status' => $status])
            ->has(OrderItem::factory()->state([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]), 'orderItems')
            ->create();
    }

    public function test_index_only_returns_the_users_own_orders(): void
    {
        $this->orderFor($this->ana);
        $this->orderFor($this->ana);
        $this->orderFor($this->juan);

        Sanctum::actingAs($this->ana);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Sanctum::actingAs($this->juan);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_owner_can_view_their_order(): void
    {
        $order = $this->orderFor($this->ana);

        Sanctum::actingAs($this->ana);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_a_stranger_cannot_view_someone_elses_order(): void
    {
        $order = $this->orderFor($this->ana);

        Sanctum::actingAs($this->juan);

        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_a_stranger_cannot_cancel_someone_elses_order(): void
    {
        $order = $this->orderFor($this->ana);

        Sanctum::actingAs($this->juan);

        $this->putJson("/api/orders/{$order->id}/cancel")->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_returns_404_when_the_order_does_not_exist(): void
    {
        Sanctum::actingAs($this->ana);

        $this->getJson('/api/orders/9999')->assertNotFound();
    }

    public function test_the_owner_cancels_a_pending_order_and_stock_is_restored(): void
    {
        $order = $this->orderFor($this->ana, quantity: 4);
        $product = $order->orderItems->first()->product;

        Sanctum::actingAs($this->ana);

        $this->putJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(54, $product->fresh()->stock);   // 50 + las 4 devueltas
    }

    public function test_a_completed_order_cannot_be_cancelled(): void
    {
        $order = $this->orderFor($this->ana, OrderStatus::Completed);

        Sanctum::actingAs($this->ana);

        $this->putJson("/api/orders/{$order->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_an_already_cancelled_order_cannot_be_cancelled(): void
    {
        $order = $this->orderFor($this->ana, OrderStatus::Cancelled);

        Sanctum::actingAs($this->ana);

        $this->putJson("/api/orders/{$order->id}/cancel")->assertUnprocessable();
    }

    public function test_order_routes_require_authentication(): void
    {
        $order = $this->orderFor($this->ana);

        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();
        $this->putJson("/api/orders/{$order->id}/cancel")->assertUnauthorized();
    }
}
