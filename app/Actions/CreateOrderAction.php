<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CreateOrderAction
{
    /**
     * Execute the order creation action.
     *
     * @param  array<string, mixed>  $data
     * @param  User|null  $user
     *
     * @throws UnprocessableEntityHttpException
     */
    public function execute(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $totalAmount = 0;
            $itemsToSync = [];

            foreach ($data['items'] as $item) {
                // Pessimistic lock to prevent race conditions on stock
                /** @var Product $product */
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock < $item['quantity']) {
                    throw new UnprocessableEntityHttpException("Insufficient stock for product: {$product->name}");
                }

                $product->decrement('stock', $item['quantity']);

                $totalAmount += $product->price * $item['quantity'];
                $itemsToSync[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                ];
            }

            $userId = $user?->id ?? $data['user_id'] ?? null;

            /** @var Order $order */
            $order = Order::create([
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'shipping_address' => $data['shipping_address'],
                'status' => 'pending',
            ]);

            $order->items()->createMany($itemsToSync);

            return $order->load('items.product');
        });
    }
}
