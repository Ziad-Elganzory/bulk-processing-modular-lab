<?php

namespace Modules\Orders\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Orders\Models\Order;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'order_id' => fake()->unique()->bothify('ORD-########'),
            'customer_id' => fake()->bothify('CUS-#####'),
            'amount' => fake()->randomFloat(2, 1, 10000),
            'currency' => 'USD',
            'order_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
