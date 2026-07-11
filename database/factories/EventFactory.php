<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(EventType::cases()),
            'subject_id' => null,
            'title' => fake()->sentence(6),
            'url' => 'https://example.com/'.fake()->unique()->slug(4),
            'summary' => fake()->optional()->paragraph(),
            'source' => fake()->randomElement([
                'huggingface', 'github', 'arxiv', 'product_hunt',
                'hacker_news', 'reddit', 'rss',
            ]),
            'payload' => ['raw' => fake()->sentence()],
            'occurred_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }
}
