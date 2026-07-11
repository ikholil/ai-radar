<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Jobs\FetchGitHubTrending;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchGitHubTrendingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured 2026-07-11 from
     * https://api.github.com/search/repositories?q=topic:llm+pushed:%3E2026-06-01&sort=stars&order=desc&per_page=5
     * (trimmed to the fields the job actually reads).
     */
    private function sampleResponse(): array
    {
        return [
            'total_count' => 35764,
            'incomplete_results' => false,
            'items' => [
                [
                    'id' => 1136590548,
                    'name' => 'ECC',
                    'full_name' => 'affaan-m/ECC',
                    'html_url' => 'https://github.com/affaan-m/ECC',
                    'description' => 'The agent harness performance optimization system.',
                    'created_at' => '2026-01-18T00:51:51Z',
                    'pushed_at' => '2026-07-09T07:53:52Z',
                    'stargazers_count' => 228328,
                    'language' => 'JavaScript',
                    'topics' => ['ai-agents', 'anthropic', 'claude', 'llm'],
                ],
                [
                    'id' => 1024554267,
                    'name' => 'hermes-agent',
                    'full_name' => 'NousResearch/hermes-agent',
                    'html_url' => 'https://github.com/NousResearch/hermes-agent',
                    'description' => 'The agent that grows with you',
                    'created_at' => '2025-07-22T22:22:28Z',
                    'pushed_at' => '2026-07-11T05:43:50Z',
                    'stargazers_count' => 212888,
                    'language' => 'Python',
                    'topics' => ['ai', 'ai-agent', 'llm'],
                ],
                [
                    'id' => 614765452,
                    'name' => 'AutoGPT',
                    'full_name' => 'Significant-Gravitas/AutoGPT',
                    'html_url' => 'https://github.com/Significant-Gravitas/AutoGPT',
                    'description' => 'AutoGPT is the vision of accessible AI for everyone.',
                    'created_at' => '2023-03-16T09:21:07Z',
                    'pushed_at' => '2026-07-11T06:14:27Z',
                    'stargazers_count' => 185460,
                    'language' => 'Python',
                    'topics' => ['agentic-ai', 'ai', 'llm'],
                ],
                [
                    'id' => 658928958,
                    'name' => 'ollama',
                    'full_name' => 'ollama/ollama',
                    'html_url' => 'https://github.com/ollama/ollama',
                    'description' => 'Get up and running with Kimi-K2.6, GLM-5.1, MiniMax, DeepSeek, gpt-oss, Qwen, Gemma and other models.',
                    'created_at' => '2023-06-26T19:39:32Z',
                    'pushed_at' => '2026-07-10T00:42:15Z',
                    'stargazers_count' => 175898,
                    'language' => 'Go',
                    'topics' => ['deepseek', 'gemma', 'llm', 'ollama'],
                ],
                [
                    'id' => 574523116,
                    'name' => 'prompts.chat',
                    'full_name' => 'f/prompts.chat',
                    'html_url' => 'https://github.com/f/prompts.chat',
                    'description' => 'f.k.a. Awesome ChatGPT Prompts.',
                    'created_at' => '2022-12-05T13:54:13Z',
                    'pushed_at' => '2026-07-11T04:05:09Z',
                    'stargazers_count' => 165354,
                    'language' => 'HTML',
                    'topics' => ['ai', 'chatgpt', 'llm', 'prompts'],
                ],
            ],
        ];
    }

    public function test_it_creates_a_subject_and_star_milestone_event_for_each_new_repo(): void
    {
        Http::fake([
            'api.github.com/search/repositories*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchGitHubTrending)->handle();

        $this->assertDatabaseCount('subjects', 5);
        $this->assertDatabaseCount('events', 5);

        $subject = Subject::where('external_url', 'https://github.com/affaan-m/ECC')->firstOrFail();
        $this->assertSame('affaan-m/ECC', $subject->name);
        $this->assertSame(SubjectKind::Repo, $subject->kind);
        $this->assertSame(228328, $subject->metric_value);
        $this->assertCount(1, $subject->metric_history);
        $this->assertSame('2026-01-18T00:51:51+00:00', $subject->first_seen_at->toIso8601String());

        $event = Event::where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame(EventType::StarMilestone, $event->type);
        $this->assertSame('github', $event->source);
        $this->assertSame('affaan-m/ECC crossed 225000 stars', $event->title);
        $this->assertSame('https://github.com/affaan-m/ECC#stars-225000', $event->url);
    }

    public function test_it_dedupes_repos_that_match_multiple_topics_in_the_same_run(): void
    {
        Http::fake([
            'api.github.com/search/repositories*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchGitHubTrending)->handle();

        // Every one of the 4 topic searches returned the same 5 repos;
        // only 5 subjects/events should exist, not 20.
        $this->assertDatabaseCount('subjects', 5);
        $this->assertDatabaseCount('events', 5);
    }

    public function test_it_updates_metrics_without_a_new_event_when_no_milestone_is_crossed(): void
    {
        Http::fake([
            'api.github.com/search/repositories*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchGitHubTrending)->handle();
        (new FetchGitHubTrending)->handle();

        $this->assertDatabaseCount('events', 5);

        $subject = Subject::where('external_url', 'https://github.com/affaan-m/ECC')->firstOrFail();
        $this->assertCount(2, $subject->metric_history);
        $this->assertSame(228328, $subject->metric_value);
    }

    public function test_it_records_a_new_event_when_a_repo_crosses_the_next_milestone(): void
    {
        $first = $this->sampleResponse();
        $updated = $first;
        $updated['items'][0]['stargazers_count'] = 251000; // crosses from 225k to 250k

        // The job makes one request per topic (4 topics), so each polling
        // round consumes 4 identical responses from the sequence.
        $sequence = Http::sequence();
        foreach (range(1, 4) as $ignored) {
            $sequence->push($first);
        }
        foreach (range(1, 4) as $ignored) {
            $sequence->push($updated);
        }

        Http::fake([
            'api.github.com/search/repositories*' => $sequence,
        ]);

        (new FetchGitHubTrending)->handle();
        (new FetchGitHubTrending)->handle();

        $this->assertDatabaseCount('events', 6);

        $subject = Subject::where('external_url', 'https://github.com/affaan-m/ECC')->firstOrFail();
        $this->assertSame(251000, $subject->metric_value);
        $this->assertDatabaseHas('events', [
            'subject_id' => $subject->id,
            'url' => 'https://github.com/affaan-m/ECC#stars-250000',
        ]);
    }
}
