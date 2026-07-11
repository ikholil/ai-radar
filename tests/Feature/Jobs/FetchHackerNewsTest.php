<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Jobs\FetchHackerNews;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchHackerNewsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured 2026-07-11 from
     * https://hn.algolia.com/api/v1/search?query=LLM&tags=story&numericFilters=points%3E50&hitsPerPage=5
     */
    private function sampleResponse(): array
    {
        return [
            'hits' => [
                [
                    'author' => 'SwoopsFromAbove',
                    '_tags' => ['story', 'author_SwoopsFromAbove', 'story_44567857'],
                    'created_at' => '2025-07-15T04:35:35Z',
                    'created_at_i' => 1752554135,
                    'num_comments' => 1628,
                    'objectID' => '44567857',
                    'points' => 1773,
                    'story_id' => 44567857,
                    'title' => 'LLM Inevitabilism',
                    'updated_at' => '2026-06-29T15:12:08Z',
                    'url' => 'https://tomrenner.com/posts/llm-inevitabilism/',
                ],
                [
                    'author' => 'fofoz',
                    '_tags' => ['story', 'author_fofoz', 'story_41523070'],
                    'created_at' => '2024-09-12T17:08:46Z',
                    'created_at_i' => 1726160926,
                    'num_comments' => 1261,
                    'objectID' => '41523070',
                    'points' => 1654,
                    'story_id' => 41523070,
                    'title' => 'Learning to Reason with LLMs',
                    'updated_at' => '2026-06-20T21:16:04Z',
                    'url' => 'https://openai.com/index/learning-to-reason-with-llms/',
                ],
                [
                    'author' => 'plibither8',
                    '_tags' => ['story', 'author_plibither8', 'story_38505211'],
                    'created_at' => '2023-12-03T06:08:29Z',
                    'created_at_i' => 1701583709,
                    'num_comments' => 131,
                    'objectID' => '38505211',
                    'points' => 1592,
                    'story_id' => 38505211,
                    'title' => 'LLM Visualization',
                    'updated_at' => '2026-06-29T01:15:50Z',
                    'url' => 'https://bbycroft.net/llm',
                ],
                [
                    'author' => 'gradus_ad',
                    '_tags' => ['story', 'author_gradus_ad', 'story_42823568'],
                    'created_at' => '2025-01-25T18:39:49Z',
                    'created_at_i' => 1737830389,
                    'num_comments' => 1056,
                    'objectID' => '42823568',
                    'points' => 1351,
                    'story_id' => 42823568,
                    'title' => 'DeepSeek-R1: Incentivizing Reasoning Capability in LLMs via RL',
                    'updated_at' => '2026-05-02T08:00:57Z',
                    'url' => 'https://arxiv.org/abs/2501.12948',
                ],
                [
                    'author' => 'meetpateltech',
                    '_tags' => ['story', 'author_meetpateltech', 'story_45529587'],
                    'created_at' => '2025-10-09T16:04:04Z',
                    'created_at_i' => 1760025844,
                    'num_comments' => 439,
                    'objectID' => '45529587',
                    'points' => 1202,
                    'story_id' => 45529587,
                    'title' => 'A small number of samples can poison LLMs of any size',
                    'updated_at' => '2026-05-08T09:57:17Z',
                    'url' => 'https://www.anthropic.com/research/small-samples-poison',
                ],
            ],
            'nbHits' => 3457,
            'page' => 0,
            'hitsPerPage' => 5,
        ];
    }

    public function test_it_creates_a_community_post_event_for_each_qualifying_story(): void
    {
        Http::fake([
            'hn.algolia.com/api/v1/search*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchHackerNews)->handle();

        $this->assertDatabaseCount('events', 5);

        $event = Event::where('url', 'https://news.ycombinator.com/item?id=44567857')->firstOrFail();
        $this->assertSame(EventType::CommunityPost, $event->type);
        $this->assertSame('LLM Inevitabilism', $event->title);
        $this->assertSame('hacker_news', $event->source);
        $this->assertNull($event->subject_id);
        $this->assertSame(1773, $event->payload['points']);
        $this->assertSame('2025-07-15T04:35:35+00:00', $event->occurred_at->toIso8601String());
    }

    public function test_it_dedupes_stories_that_match_multiple_keywords_in_the_same_run(): void
    {
        Http::fake([
            'hn.algolia.com/api/v1/search*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchHackerNews)->handle();

        // Every one of the 5 keyword searches returned the same 5 hits;
        // only 5 events should have been created, not 25.
        $this->assertDatabaseCount('events', 5);
    }

    public function test_it_does_not_create_duplicate_events_on_repeat_polls(): void
    {
        Http::fake([
            'hn.algolia.com/api/v1/search*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchHackerNews)->handle();
        (new FetchHackerNews)->handle();

        $this->assertDatabaseCount('events', 5);
    }
}
