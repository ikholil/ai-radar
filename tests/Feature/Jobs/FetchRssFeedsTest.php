<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Jobs\FetchRssFeeds;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchRssFeedsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured 2026-07-11 from https://simonwillison.net/atom/everything/
     * (second entry's summary was truncated in capture, so it's a short
     * placeholder here - title, link, and published date are real).
     */
    private function sampleAtomFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<feed xml:lang="en-us" xmlns="http://www.w3.org/2005/Atom"><title>Simon Willison's Weblog</title><link href="http://simonwillison.net/" rel="alternate"/><link href="http://simonwillison.net/atom/everything/" rel="self"/><id>http://simonwillison.net/</id><updated>2026-07-10T17:05:26+00:00</updated><author><name>Simon Willison</name></author><entry><title>Quoting Nilay Patel</title><link href="https://simonwillison.net/2026/Jul/10/nilay-patel/#atom-everything" rel="alternate"/><published>2026-07-10T17:05:26+00:00</published><updated>2026-07-10T17:05:26+00:00</updated><id>https://simonwillison.net/2026/Jul/10/nilay-patel/#atom-everything</id><summary type="html">
    &lt;blockquote cite="https://youtu.be/v4vkwUf4AMw?t=2427"&gt;&lt;p&gt;The reality is to make augmented reality glasses, you need to put a camera next to your eyes.&lt;/p&gt;&lt;/blockquote&gt;
    &lt;p&gt;Tags: &lt;a href="https://simonwillison.net/tags/ai-ethics"&gt;ai-ethics&lt;/a&gt;&lt;/p&gt;
</summary><category term="ai-ethics"/><category term="augmented-reality"/><category term="nilay-patel"/><category term="privacy"/><category term="ai"/></entry><entry><title>Quoting OpenAI</title><link href="https://simonwillison.net/2026/Jul/10/openai/#atom-everything" rel="alternate"/><published>2026-07-10T01:05:57+00:00</published><updated>2026-07-10T01:05:57+00:00</updated><id>https://simonwillison.net/2026/Jul/10/openai/#atom-everything</id><summary type="html">Work on web and mobile runs in the cloud.</summary><category term="ai"/></entry></feed>
XML;
    }

    /**
     * Representative RSS 2.0 shape (Atom is the only format captured live
     * from a real feed so far; this exercises the parser's other branch).
     */
    private function sampleRssFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Example AI Blog</title><link>https://example.com/blog</link><item><title>Announcing a new model</title><link>https://example.com/blog/new-model</link><description>&lt;p&gt;We shipped a new model today.&lt;/p&gt;</description><pubDate>Fri, 10 Jul 2026 12:00:00 GMT</pubDate></item></channel></rss>
XML;
    }

    public function test_it_creates_blog_post_events_from_an_atom_feed(): void
    {
        Http::fake([
            'simonwillison.net/*' => Http::response($this->sampleAtomFeed()),
        ]);

        (new FetchRssFeeds)->handle();

        $this->assertDatabaseCount('events', 2);

        $event = Event::where('url', 'https://simonwillison.net/2026/Jul/10/nilay-patel/#atom-everything')->firstOrFail();
        $this->assertSame(EventType::BlogPost, $event->type);
        $this->assertSame('Quoting Nilay Patel', $event->title);
        $this->assertSame('blog_simonwillison', $event->source);
        $this->assertNull($event->subject_id);
        $this->assertStringContainsString('augmented reality glasses', $event->summary);
        $this->assertSame('2026-07-10T17:05:26+00:00', $event->occurred_at->toIso8601String());
    }

    public function test_it_does_not_create_duplicate_events_on_repeat_polls(): void
    {
        Http::fake([
            'simonwillison.net/*' => Http::response($this->sampleAtomFeed()),
        ]);

        (new FetchRssFeeds)->handle();
        (new FetchRssFeeds)->handle();

        $this->assertDatabaseCount('events', 2);
    }

    public function test_it_parses_rss_two_point_o_items(): void
    {
        // Swap in a plain RSS feed via reflection-free approach: fake the
        // same configured URL to return RSS instead of Atom.
        Http::fake([
            'simonwillison.net/*' => Http::response($this->sampleRssFeed()),
        ]);

        (new FetchRssFeeds)->handle();

        $this->assertDatabaseCount('events', 1);

        $event = Event::where('url', 'https://example.com/blog/new-model')->firstOrFail();
        $this->assertSame('Announcing a new model', $event->title);
        $this->assertSame('We shipped a new model today.', $event->summary);
    }
}
