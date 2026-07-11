<?php

namespace App\Jobs\Concerns;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

trait TracksGitHubRepoStars
{
    private const MILESTONE_STEP = 25000;

    private const HISTORY_LIMIT = 30;

    private function githubClient()
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
                'metric_history' => [$this->starSnapshot($stars)],
                'first_seen_at' => Carbon::parse($repo['created_at'] ?? now()),
            ]);

            $this->recordStarMilestone($subject, $repo, $currentMilestone);

            return;
        }

        $previousMilestone = intdiv($subject->metric_value ?? 0, self::MILESTONE_STEP);
        $history = [...($subject->metric_history ?? []), $this->starSnapshot($stars)];

        $subject->update([
            'metric_value' => $stars,
            'metric_history' => array_slice($history, -self::HISTORY_LIMIT),
        ]);

        if ($currentMilestone > $previousMilestone) {
            $this->recordStarMilestone($subject, $repo, $currentMilestone);
        }
    }

    private function recordStarMilestone(Subject $subject, array $repo, int $milestone): void
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

    private function starSnapshot(int $stars): array
    {
        return [
            'stars' => $stars,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
