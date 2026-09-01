<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

test('anyone can list orders with cursor pagination', function () {
    $orders = Order::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'total_amount',
                    'shipping_address',
                    'items',
                    'created_at',
                ],
            ],
            'links',
            'meta',
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('anyone can create an order without authentication', function () {
    $product1 = Product::factory()->create([
        'name' => 'Mechanical Keyboard',
        'price' => 150.00,
        'stock' => 10,
    ]);
    $product2 = Product::factory()->create([
        'name' => 'Gaming Mouse',
        'price' => 50.00,
        'stock' => 20,
    ]);

    $payload = [
        'shipping_address' => '123 Main St, Tech City, TC 10001',
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 1],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $payload)
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'total_amount',
                'shipping_address',
                'items' => [
                    '*' => [
                        'id',
                        'product_id',
                        'quantity',
                        'unit_price',
                        'subtotal',
                        'product' => [
                            'id',
                            'name',
                            'price',
                            'stock',
                        ],
                    ],
                ],
                'created_at',
            ],
        ]);

    // Total should be (150 * 2) + (50 * 1) = 350.00
    expect($response->json('data.total_amount'))->toEqual(350.00);
    expect($response->json('data.status'))->toEqual('pending');
    expect($response->json('data.shipping_address'))->toEqual('123 Main St, Tech City, TC 10001');
    expect($response->json('data.items'))->toHaveCount(2);

    // Verify stock decrement in database
    expect($product1->fresh()->stock)->toEqual(8);
    expect($product2->fresh()->stock)->toEqual(19);

    // Verify database record
    $this->assertDatabaseHas('orders', [
        'total_amount' => 350.00,
        'shipping_address' => '123 Main St, Tech City, TC 10001',
        'status' => 'pending',
    ]);
});

test('creating an order fails when validation rules are violated', function () {
    $this->postJson('/api/v1/orders', [
        'shipping_address' => '',
        'items' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_address', 'items']);

    $this->postJson('/api/v1/orders', [
        'shipping_address' => 'Valid Address',
        'items' => [
            ['product_id' => 99999, 'quantity' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
});

test('creating an order fails and rolls back transaction if stock is insufficient', function () {
    $product = Product::factory()->create([
        'name' => 'Limited Edition GPU',
        'price' => 999.99,
        'stock' => 2,
    ]);

    $payload = [
        'shipping_address' => '456 Elm St, City, ST 12345',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $payload)
        ->assertStatus(422)
        ->assertJson([
            'message' => "Insufficient stock for product: {$product->name}",
        ]);

    // Stock must remain intact
    expect($product->fresh()->stock)->toEqual(2);

    // No order should have been created
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
});

test('anyone can view a single order by ID', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 25.00,
    ]);

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.items.0.product_id', $product->id);
});

test('anyone can update an order status or shipping address', function () {
    $order = Order::factory()->create([
        'status' => 'pending',
        'shipping_address' => 'Old Address',
    ]);

    $this->putJson("/api/v1/orders/{$order->id}", [
        'status' => 'processing',
        'shipping_address' => 'Updated New Address',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.shipping_address', 'Updated New Address');

    expect($order->fresh()->status)->toEqual('processing');
    expect($order->fresh()->shipping_address)->toEqual('Updated New Address');
});

test('anyone can delete an order', function () {
    $order = Order::factory()->create();

    $this->deleteJson("/api/v1/orders/{$order->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);
});
