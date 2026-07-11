<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_loads_successfully_with_no_data(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('AI RADAR');
        $response->assertSee("haven't run", false);
    }

    public function test_it_shows_recent_events_in_the_feed(): void
    {
        $subject = Subject::factory()->create(['name' => 'meta-llama/Llama-4-70B']);
        Event::factory()->create([
            'type' => EventType::Release,
            'title' => 'Llama 4 70B released',
            'source' => 'huggingface',
            'subject_id' => $subject->id,
            'occurred_at' => now()->subMinutes(5),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Llama 4 70B released');
        $response->assertSee('meta-llama/Llama-4-70B');
        $response->assertSee('huggingface');
    }

    public function test_it_shows_trending_subjects_ranked_by_metric_value(): void
    {
        Subject::factory()->create(['name' => 'low-star-repo', 'metric_value' => 100]);
        Subject::factory()->create(['name' => 'high-star-repo', 'metric_value' => 999999]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['high-star-repo', 'low-star-repo']);
    }

    public function test_it_shows_new_arrivals_from_the_last_seven_days(): void
    {
        // Both subjects would land in the "Trending" section too (there's
        // nothing else to outrank them), so assert on the view data
        // directly rather than raw page text.
        Subject::factory()->create([
            'name' => 'brand-new-tool',
            'kind' => SubjectKind::Tool,
            'first_seen_at' => now()->subDays(2),
        ]);
        Subject::factory()->create([
            'name' => 'ancient-tool',
            'kind' => SubjectKind::Tool,
            'first_seen_at' => now()->subDays(30),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('newArrivals', function ($newArrivals) {
            $names = $newArrivals->pluck('name');

            return $names->contains('brand-new-tool') && ! $names->contains('ancient-tool');
        });
    }
}
