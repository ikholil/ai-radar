<?php

namespace App\Jobs;

use App\Jobs\Concerns\TracksGitHubRepoStars;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchGitHubTrending implements ShouldQueue
{
    use Queueable, TracksGitHubRepoStars;

    public int $timeout = 30;

    public int $tries = 3;

    private const ENDPOINT = 'https://api.github.com/search/repositories';

    private const TOPICS = ['llm', 'ai-agents', 'machine-learning', 'generative-ai'];

    private const PER_PAGE = 20;

    private const ACTIVE_WITHIN_DAYS = 30;

    public function handle(): void
    {
        // Github has no official "trending" endpoint; searching by topic,
        // sorted by stars, and filtered to recently-pushed repos is the
        // closest public proxy for it.
        $seenIds = [];
        $activeSince = now()->subDays(self::ACTIVE_WITHIN_DAYS)->toDateString();

        foreach (self::TOPICS as $topic) {
            $items = $this->githubClient()
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
}
