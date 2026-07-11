<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FetchGitHubTrending implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    private const ENDPOINT = 'https://api.github.com/search/repositories';

    private const TOPICS = ['llm', 'ai-agents', 'machine-learning', 'generative-ai'];

    private const PER_PAGE = 20;

    private const ACTIVE_WITHIN_DAYS = 30;

    private const MILESTONE_STEP = 25000;

    private const HISTORY_LIMIT = 30;

    public function handle(): void
    {
        // Github has no official "trending" endpoint; searching by topic,
        // sorted by stars, and filtered to recently-pushed repos is the
        // closest public proxy for it.
        $seenIds = [];
        $activeSince = now()->subDays(self::ACTIVE_WITHIN_DAYS)->toDateString();

        foreach (self::TOPICS as $topic) {
            $items = $this->client()
                ->get(self::ENDPOINT, [
                    'q' => "topic:{$topic} pushed:>{$activeSince}",
                    'sort' => 'stars',
                    'order' => 'desc',
                    'per_page' => self::PER_PAGE,
                ])
                ->throw()
                ->json('items');

            foreach ($items as $repo) {
                if (isset($seenIds[$repo['id']])) {
                    continue;
                }
                $seenIds[$repo['id']] = true;

                $this->processRepo($repo);
            }
        }
    }

    private function client()
    {
        $token = config('services.github.token');

        return Http::timeout($this->timeout)->withHeaders(array_filter([
            'Accept' => 'application/vnd.github+json',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ]));
    }

    private function processRepo(array $repo): void
    {
        $externalUrl = $repo['html_url'];
        $stars = $repo['stargazers_count'] ?? 0;
        $currentMilestone = intdiv($stars, self::MILESTONE_STEP);

        $subject = Subject::where('external_url', $externalUrl)->first();

        if (! $subject) {
            $subject = Subject::create([
                'name' => $repo['full_name'],
                'kind' => SubjectKind::Repo,
                'external_url' => $externalUrl,
                'metric_value' => $stars,
                'metric_history' => [$this->snapshot($stars)],
                'first_seen_at' => Carbon::parse($repo['created_at'] ?? now()),
            ]);

            $this->recordMilestone($subject, $repo, $currentMilestone);

            return;
        }

        $previousMilestone = intdiv($subject->metric_value ?? 0, self::MILESTONE_STEP);
        $history = [...($subject->metric_history ?? []), $this->snapshot($stars)];

        $subject->update([
            'metric_value' => $stars,
            'metric_history' => array_slice($history, -self::HISTORY_LIMIT),
        ]);

        if ($currentMilestone > $previousMilestone) {
            $this->recordMilestone($subject, $repo, $currentMilestone);
        }
    }

    private function recordMilestone(Subject $subject, array $repo, int $milestone): void
    {
        $milestoneStars = $milestone * self::MILESTONE_STEP;

        Event::firstOrCreate(
            [
                'source' => 'github',
                'url' => $repo['html_url'].'#stars-'.$milestoneStars,
            ],
            [
                'type' => EventType::StarMilestone,
                'subject_id' => $subject->id,
                'title' => "{$repo['full_name']} crossed {$milestoneStars} stars",
                'summary' => $repo['description'] ?? null,
                'payload' => $repo,
                'occurred_at' => now(),
            ]
        );
    }

    private function snapshot(int $stars): array
    {
        return [
            'stars' => $stars,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
