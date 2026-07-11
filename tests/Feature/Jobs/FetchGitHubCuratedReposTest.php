<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Jobs\FetchGitHubCuratedRepos;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchGitHubCuratedReposTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shape verified real against
     * https://api.github.com/search/repositories?q=topic:llm...&sort=stars
     * (same endpoint this job calls with q=org:X instead); items below are
     * a plausible pair of repos per org built on that verified schema.
     */
    private function orgResponse(string $repoName, string $fullName, int $stars): array
    {
        return [
            'total_count' => 1,
            'incomplete_results' => false,
            'items' => [
                [
                    'id' => crc32($fullName),
                    'name' => $repoName,
                    'full_name' => $fullName,
                    'html_url' => "https://github.com/{$fullName}",
                    'description' => "{$repoName} repository",
                    'created_at' => '2021-05-01T00:00:00Z',
                    'pushed_at' => '2026-07-10T00:00:00Z',
                    'stargazers_count' => $stars,
                    'language' => 'Python',
                    'topics' => ['ai'],
                ],
            ],
        ];
    }

    public function test_it_tracks_the_top_repo_for_each_configured_org(): void
    {
        Http::fake([
            'api.github.com/search/repositories*q=org%3Aopenai*' => Http::response(
                $this->orgResponse('openai-python', 'openai/openai-python', 25100)
            ),
            'api.github.com/search/repositories*q=org%3Aollama*' => Http::response(
                $this->orgResponse('ollama', 'ollama/ollama', 175898)
            ),
            '*' => Http::response(['total_count' => 0, 'incomplete_results' => false, 'items' => []]),
        ]);

        (new FetchGitHubCuratedRepos)->handle();

        $openai = Subject::where('external_url', 'https://github.com/openai/openai-python')->firstOrFail();
        $this->assertSame(SubjectKind::Repo, $openai->kind);
        $this->assertSame(25100, $openai->metric_value);

        $ollama = Subject::where('external_url', 'https://github.com/ollama/ollama')->firstOrFail();
        $this->assertSame(175898, $ollama->metric_value);

        $event = Event::where('subject_id', $openai->id)->firstOrFail();
        $this->assertSame(EventType::StarMilestone, $event->type);
        $this->assertSame('github', $event->source);
        $this->assertSame('openai/openai-python crossed 25000 stars', $event->title);
    }

    public function test_it_shares_subjects_with_the_topic_trending_job_via_external_url(): void
    {
        // A repo that shows up both via org search and topic search should
        // resolve to one subject, not two, since dedup keys on external_url.
        Http::fake([
            '*' => Http::response($this->orgResponse('ollama', 'ollama/ollama', 175898)),
        ]);

        (new FetchGitHubCuratedRepos)->handle();
        (new FetchGitHubCuratedRepos)->handle();

        $this->assertSame(
            1,
            Subject::where('external_url', 'https://github.com/ollama/ollama')->count()
        );
    }

    public function test_it_does_not_duplicate_subjects_across_orgs_matching_the_same_repo(): void
    {
        Http::fake([
            '*' => Http::response($this->orgResponse('shared-repo', 'shared/shared-repo', 30000)),
        ]);

        (new FetchGitHubCuratedRepos)->handle();

        // Every one of the 50 org queries returned the same repo id; only
        // one subject/event should have been created.
        $this->assertSame(1, Subject::count());
        $this->assertSame(1, Event::count());
    }
}
