<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'details' => '(wyd. Bellona)',
            'is_purchased' => false,
        ];
    }

    public function purchased(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_purchased' => true,
        ]);
    }
}
