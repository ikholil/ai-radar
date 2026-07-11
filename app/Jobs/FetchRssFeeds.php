<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;

class FetchRssFeeds implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /**
     * Additional feeds (OpenAI, Anthropic, Google AI blog) are pending a
     * confirmed working URL - their published addresses either redirect
     * or 404 as of this writing.
     */
    private const FEEDS = [
        ['source' => 'blog_simonwillison', 'url' => 'https://simonwillison.net/atom/everything/'],
    ];

    public function handle(): void
    {
        foreach (self::FEEDS as $feed) {
            $body = Http::timeout($this->timeout)
                ->get($feed['url'])
                ->throw()
                ->body();

            foreach ($this->parseEntries($body) as $entry) {
                $this->processEntry($feed['source'], $entry);
            }
        }
    }

    /**
     * @return array<int, array{title: string, url: string, summary: ?string, published_at: string}>
     */
    private function parseEntries(string $xml): array
    {
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        return $doc->getName() === 'feed'
            ? $this->parseAtomEntries($doc)
            : $this->parseRssItems($doc);
    }

    private function parseAtomEntries(SimpleXMLElement $feed): array
    {
        $entries = [];

        foreach ($feed->entry as $entry) {
            $link = (string) ($entry->link['href'] ?? '');

            foreach ($entry->link as $linkEl) {
                if ((string) ($linkEl['rel'] ?? 'alternate') === 'alternate') {
                    $link = (string) $linkEl['href'];
                    break;
                }
            }

            $entries[] = [
                'title' => (string) $entry->title,
                'url' => $link,
                'summary' => $this->cleanSummary((string) $entry->summary),
                'published_at' => (string) ($entry->published ?: $entry->updated),
            ];
        }

        return $entries;
    }

    private function parseRssItems(SimpleXMLElement $rss): array
    {
        $entries = [];

        foreach ($rss->channel->item as $item) {
            $entries[] = [
                'title' => (string) $item->title,
                'url' => (string) $item->link,
                'summary' => $this->cleanSummary((string) $item->description),
                'published_at' => (string) $item->pubDate,
            ];
        }

        return $entries;
    }

    private function cleanSummary(string $summary): ?string
    {
        $text = trim(strip_tags($summary));

        return $text === '' ? null : Str::limit($text, 1000);
    }

    private function processEntry(string $source, array $entry): void
    {
        if ($entry['url'] === '') {
            return;
        }

        Event::firstOrCreate(
            ['source' => $source, 'url' => $entry['url']],
            [
                'type' => EventType::BlogPost,
                'subject_id' => null,
                'title' => $entry['title'] !== '' ? $entry['title'] : '(untitled)',
                'summary' => $entry['summary'],
                'payload' => $entry,
                'occurred_at' => Carbon::parse($entry['published_at'] ?: now()),
            ]
        );
    }
}
