<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FetchHackerNews implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    private const ENDPOINT = 'https://hn.algolia.com/api/v1/search';

    private const KEYWORDS = ['AI', 'LLM', 'GPT', 'machine learning', 'artificial intelligence'];

    private const MIN_POINTS = 50;

    private const HITS_PER_PAGE = 20;

    public function handle(): void
    {
        // Multiple keyword searches can surface the same story; dedupe
        // within a single run before touching the database.
        $seenObjectIds = [];

        foreach (self::KEYWORDS as $keyword) {
            $hits = Http::timeout($this->timeout)
                ->get(self::ENDPOINT, [
                    'query' => $keyword,
                    'tags' => 'story',
                    'numericFilters' => 'points>'.self::MIN_POINTS,
                    'hitsPerPage' => self::HITS_PER_PAGE,
                ])
                ->throw()
                ->json('hits');

            foreach ($hits as $hit) {
                if (isset($seenObjectIds[$hit['objectID']])) {
                    continue;
                }
                $seenObjectIds[$hit['objectID']] = true;

                $this->processHit($hit);
            }
        }
    }

    private function processHit(array $hit): void
    {
        Event::firstOrCreate(
            [
                'source' => 'hacker_news',
                'url' => 'https://news.ycombinator.com/item?id='.$hit['objectID'],
            ],
            [
                'type' => EventType::CommunityPost,
                'subject_id' => null,
                'title' => $hit['title'] ?? '(untitled)',
                'summary' => null,
                'payload' => $hit,
                'occurred_at' => Carbon::parse($hit['created_at'] ?? now()),
            ]
        );
    }
}
