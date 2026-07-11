<?php

namespace Database\Factories;

use App\Enums\SubjectKind;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $name = fake()->company().'/'.fake()->slug(2);

        return [
            'name' => $name,
            'kind' => fake()->randomElement(SubjectKind::cases()),
            'external_url' => 'https://example.com/'.fake()->unique()->slug(3),
            'metric_value' => fake()->numberBetween(0, 200000),
            'metric_history' => collect(range(1, fake()->numberBetween(1, 5)))
                ->map(fn (int $i) => [
                    'value' => fake()->numberBetween(0, 200000),
                    'recorded_at' => now()->subHours($i)->toIso8601String(),
                ])
                ->all(),
            'first_seen_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }

    public function newArrival(): static
    {
        return $this->state(fn () => [
            'first_seen_at' => fake()->dateTimeBetween('-6 days'),
        ]);
    }
}
