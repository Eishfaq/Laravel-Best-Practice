<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Product::factory()->createMany([
            [
                'name' => 'Wireless Noise-Canceling Headphones',
                'price' => 199.99,
                'stock' => 50,
            ],
            [
                'name' => 'Mechanical Gaming Keyboard RGB',
                'price' => 89.50,
                'stock' => 100,
            ],
            [
                'name' => 'Ergonomic Vertical Mouse',
                'price' => 45.00,
                'stock' => 75,
            ],
            [
                'name' => '4K Ultra HD IPS Monitor 27-inch',
                'price' => 349.99,
                'stock' => 20,
            ],
        ]);
    }
}
