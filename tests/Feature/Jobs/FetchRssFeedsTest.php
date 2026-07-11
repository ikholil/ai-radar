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

    /**
     * A 3-entry subset captured 2026-07-11 from
     * https://www.reddit.com/r/LocalLLaMA/new.rss (real title/link/content/dates).
     */
    private function sampleRedditFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><category term="LocalLLaMA" label="r/LocalLLaMA"/><updated>2026-07-11T06:11:15+00:00</updated><id>/r/LocalLLaMA/new.rss</id><title>newest submissions : LocalLLaMA</title><entry><author><name>/u/Time-Toe-1276</name><uri>https://www.reddit.com/user/Time-Toe-1276</uri></author><category term="LocalLLaMA" label="r/LocalLLaMA"/><content type="html">&lt;!-- SC_OFF --&gt;&lt;div class=&quot;md&quot;&gt;&lt;p&gt;So, umhh, I am working on an agentic coding platform, and I need to make qwen3.5 and gemma4 models out of controlled reasoning chains.&lt;/p&gt;&lt;/div&gt;&lt;!-- SC_ON --&gt;</content><id>t3_1utbros</id><link href="https://www.reddit.com/r/LocalLLaMA/comments/1utbros/how_can_i_limit_reasoning_effort_on_the_qwen35/" /><updated>2026-07-11T05:57:00+00:00</updated><published>2026-07-11T05:57:00+00:00</published><title>How can i limit reasoning effort on the qwen3.5 and gemma4 models?</title></entry><entry><author><name>/u/IUseClifford</name><uri>https://www.reddit.com/user/IUseClifford</uri></author><category term="LocalLLaMA" label="r/LocalLLaMA"/><content type="html">&lt;!-- SC_OFF --&gt;&lt;div class=&quot;md&quot;&gt;&lt;p&gt;Website - &lt;a href=&quot;https://clifford.bot/&quot;&gt;https://clifford.bot/&lt;/a&gt;&lt;/p&gt;&lt;p&gt;Clifford is a tool/daemon which allows users to save existing local AI configurations and reload them with an ergonomic CLI.&lt;/p&gt;&lt;/div&gt;&lt;!-- SC_ON --&gt;</content><id>t3_1ut3z4y</id><link href="https://www.reddit.com/r/LocalLLaMA/comments/1ut3z4y/clifford_control_plane_cli_for_local_ai/" /><updated>2026-07-10T23:37:29+00:00</updated><published>2026-07-10T23:37:29+00:00</published><title>Clifford - Control Plane CLI for Local AI</title></entry><entry><author><name>/u/SpicyWangz</name><uri>https://www.reddit.com/user/SpicyWangz</uri></author><category term="LocalLLaMA" label="r/LocalLLaMA"/><content type="html">&lt;!-- SC_OFF --&gt;&lt;div class=&quot;md&quot;&gt;&lt;p&gt;I used to check LM Arena anytime new open models came out to see how they stacked up to the big closed ones.&lt;/p&gt;&lt;p&gt;But it seems like they’ve really cut back on displaying any newly released open models other than the largest.&lt;/p&gt;&lt;/div&gt;&lt;!-- SC_ON --&gt;</content><id>t3_1ut0n1p</id><link href="https://www.reddit.com/r/LocalLLaMA/comments/1ut0n1p/is_lm_arena_over/" /><updated>2026-07-10T21:22:35+00:00</updated><published>2026-07-10T21:22:35+00:00</published><title>Is LM Arena over?</title></entry></feed>
XML;
    }

    public function test_it_creates_blog_post_events_from_an_atom_feed(): void
    {
        Http::fake([
            'simonwillison.net/*' => Http::response($this->sampleAtomFeed()),
            '*' => Http::response(''),
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
            '*' => Http::response(''),
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
            '*' => Http::response(''),
        ]);

        (new FetchRssFeeds)->handle();

        $this->assertDatabaseCount('events', 1);

        $event = Event::where('url', 'https://example.com/blog/new-model')->firstOrFail();
        $this->assertSame('Announcing a new model', $event->title);
        $this->assertSame('We shipped a new model today.', $event->summary);
    }

    public function test_it_creates_community_post_events_from_a_reddit_feed(): void
    {
        Http::fake([
            'reddit.com/r/LocalLLaMA/*' => Http::response($this->sampleRedditFeed()),
            '*' => Http::response(''),
        ]);

        (new FetchRssFeeds)->handle();

        $this->assertDatabaseCount('events', 3);

        $event = Event::where('url', 'https://www.reddit.com/r/LocalLLaMA/comments/1ut0n1p/is_lm_arena_over/')->firstOrFail();
        $this->assertSame(EventType::CommunityPost, $event->type);
        $this->assertSame('Is LM Arena over?', $event->title);
        $this->assertSame('reddit_LocalLLaMA', $event->source);
        $this->assertNull($event->subject_id);
        $this->assertStringContainsString('LM Arena', $event->summary);
        $this->assertSame('2026-07-10T21:22:35+00:00', $event->occurred_at->toIso8601String());
    }

    public function test_it_fetches_all_configured_feeds_in_one_run(): void
    {
        Http::fake([
            'simonwillison.net/*' => Http::response($this->sampleAtomFeed()),
            'reddit.com/r/LocalLLaMA/*' => Http::response($this->sampleRedditFeed()),
            '*' => Http::response(''),
        ]);

        (new FetchRssFeeds)->handle();

        // 2 from Simon Willison + 3 from r/LocalLLaMA; the other two
        // configured subreddits get the default empty fake response.
        $this->assertDatabaseCount('events', 5);
    }
}
