<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Models\Event;
use App\Models\Source;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_source_can_be_created_with_valid_data(): void
    {
        $source = Source::factory()->create([
            'name' => 'huggingface',
            'type' => 'api',
            'poll_interval_minutes' => 10,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('sources', [
            'id' => $source->id,
            'name' => 'huggingface',
            'type' => 'api',
            'poll_interval_minutes' => 10,
        ]);
        $this->assertTrue($source->enabled);
        $this->assertNull($source->fresh()->last_polled_at);
    }

    public function test_a_subject_can_be_created_with_valid_data(): void
    {
        $subject = Subject::factory()->create([
            'name' => 'meta-llama/Llama-4-70B',
            'kind' => SubjectKind::Model,
            'external_url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
            'metric_value' => 123456,
            'metric_history' => [
                ['value' => 120000, 'recorded_at' => '2026-07-10T12:00:00+00:00'],
                ['value' => 123456, 'recorded_at' => '2026-07-11T12:00:00+00:00'],
            ],
        ]);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'meta-llama/Llama-4-70B',
            'kind' => 'model',
        ]);

        $fresh = $subject->fresh();
        $this->assertSame(SubjectKind::Model, $fresh->kind);
        $this->assertIsArray($fresh->metric_history);
        $this->assertCount(2, $fresh->metric_history);
        $this->assertSame(123456, $fresh->metric_value);
        $this->assertNotNull($fresh->first_seen_at);
    }

    public function test_an_event_can_be_created_with_valid_data(): void
    {
        $event = Event::factory()->create([
            'type' => EventType::Release,
            'title' => 'Llama 4 70B released',
            'url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
            'source' => 'huggingface',
            'payload' => ['downloads' => 42],
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'type' => 'release',
            'source' => 'huggingface',
        ]);

        $fresh = $event->fresh();
        $this->assertSame(EventType::Release, $fresh->type);
        $this->assertSame(['downloads' => 42], $fresh->payload);
        $this->assertNotNull($fresh->occurred_at);
    }

    public function test_an_event_can_belong_to_a_subject(): void
    {
        $subject = Subject::factory()->create();
        $event = Event::factory()->create(['subject_id' => $subject->id]);

        $this->assertTrue($event->subject->is($subject));
        $this->assertTrue($subject->events->first()->is($event));
    }

    public function test_an_event_subject_is_optional(): void
    {
        $event = Event::factory()->create(['subject_id' => null]);

        $this->assertNull($event->subject);
    }

    public function test_deleting_a_subject_nulls_out_its_events_rather_than_deleting_them(): void
    {
        $subject = Subject::factory()->create();
        $event = Event::factory()->create(['subject_id' => $subject->id]);

        $subject->delete();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'subject_id' => null]);
    }

    public function test_duplicate_source_and_url_events_are_rejected(): void
    {
        Event::factory()->create([
            'source' => 'huggingface',
            'url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
        ]);

        $this->expectException(QueryException::class);

        Event::factory()->create([
            'source' => 'huggingface',
            'url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
        ]);
    }

    public function test_the_same_url_from_different_sources_is_allowed(): void
    {
        Event::factory()->create([
            'source' => 'huggingface',
            'url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
        ]);

        Event::factory()->create([
            'source' => 'hacker_news',
            'url' => 'https://huggingface.co/meta-llama/Llama-4-70B',
        ]);

        $this->assertDatabaseCount('events', 2);
    }

    public function test_source_names_are_unique(): void
    {
        Source::factory()->create(['name' => 'arxiv']);

        $this->expectException(QueryException::class);

        Source::factory()->create(['name' => 'arxiv']);
    }

    public function test_all_enum_values_round_trip_through_the_database(): void
    {
        foreach (SubjectKind::cases() as $kind) {
            $subject = Subject::factory()->create(['kind' => $kind]);
            $this->assertSame($kind, $subject->fresh()->kind);
        }

        foreach (EventType::cases() as $type) {
            $event = Event::factory()->create(['type' => $type]);
            $this->assertSame($type, $event->fresh()->type);
        }
    }
}
