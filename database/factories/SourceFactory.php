<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'huggingface', 'github', 'arxiv', 'product_hunt',
                'hacker_news', 'reddit', 'rss',
            ]).'_'.fake()->unique()->numberBetween(1, 100000),
            'type' => fake()->randomElement(['api', 'rss']),
            'last_polled_at' => null,
            'poll_interval_minutes' => fake()->randomElement([10, 15, 20, 30]),
            'enabled' => true,
        ];
    }

    public function polled(): static
    {
        return $this->state(fn () => [
            'last_polled_at' => fake()->dateTimeBetween('-1 hour'),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
