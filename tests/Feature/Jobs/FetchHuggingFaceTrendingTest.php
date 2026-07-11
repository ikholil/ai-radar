<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Enums\SubjectKind;
use App\Jobs\FetchHuggingFaceTrending;
use App\Models\Event;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchHuggingFaceTrendingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured 2026-07-11 from https://huggingface.co/api/models?limit=5
     */
    private function sampleResponse(): array
    {
        return [
            [
                '_id' => '6a46666a9df197cf7b7bed77',
                'id' => 'tencent/Hy3',
                'likes' => 677,
                'trendingScore' => 650,
                'private' => false,
                'downloads' => 6923,
                'tags' => ['transformers', 'safetensors', 'hy_v3', 'text-generation', 'hunyuan', 'hy3', 'moe', 'conversational', 'license:apache-2.0', 'eval-results', 'endpoints_compatible', 'region:us'],
                'pipeline_tag' => 'text-generation',
                'library_name' => 'transformers',
                'createdAt' => '2026-07-02T13:23:54.000Z',
                'modelId' => 'tencent/Hy3',
            ],
            [
                '_id' => '6a3552eea519cd3014351343',
                'id' => 'empero-ai/Qwythos-9B-Claude-Mythos-5-1M-GGUF',
                'likes' => 1984,
                'trendingScore' => 499,
                'private' => false,
                'downloads' => 1909705,
                'tags' => ['gguf', 'llama.cpp', 'quantized', 'qwen3.5', 'reasoning', 'uncensored', 'long-context', '1M-context', 'function-calling', 'multimodal', 'vision', 'cybersecurity', 'biomedical', 'agentic', 'image-text-to-text', 'en', 'base_model:empero-ai/Qwythos-9B-Claude-Mythos-5-1M', 'base_model:quantized:empero-ai/Qwythos-9B-Claude-Mythos-5-1M', 'license:apache-2.0', 'endpoints_compatible', 'region:us', 'conversational'],
                'pipeline_tag' => 'image-text-to-text',
                'library_name' => 'gguf',
                'createdAt' => '2026-06-19T14:32:14.000Z',
                'modelId' => 'empero-ai/Qwythos-9B-Claude-Mythos-5-1M-GGUF',
            ],
            [
                '_id' => '6a30fda88514bfa5f01a33b6',
                'id' => 'zai-org/GLM-5.2',
                'likes' => 3795,
                'trendingScore' => 344,
                'private' => false,
                'downloads' => 392655,
                'tags' => ['transformers', 'safetensors', 'glm_moe_dsa', 'text-generation', 'conversational', 'en', 'zh', 'arxiv:2602.15763', 'arxiv:2603.12201', 'license:mit', 'eval-results', 'endpoints_compatible', 'region:us'],
                'pipeline_tag' => 'text-generation',
                'library_name' => 'transformers',
                'createdAt' => '2026-06-16T07:39:20.000Z',
                'modelId' => 'zai-org/GLM-5.2',
            ],
            [
                '_id' => '6a389c831278d868e03055c2',
                'id' => 'InternScience/Agents-A1',
                'likes' => 476,
                'trendingScore' => 254,
                'private' => false,
                'downloads' => 25772,
                'tags' => ['transformers', 'safetensors', 'qwen3_5_moe', 'image-text-to-text', 'moe', 'vlm', 'vision', 'agentic', 'text-generation', 'conversational', 'arxiv:2606.30616', 'license:apache-2.0', 'eval-results', 'endpoints_compatible', 'region:us'],
                'pipeline_tag' => 'text-generation',
                'library_name' => 'transformers',
                'createdAt' => '2026-06-22T02:22:59.000Z',
                'modelId' => 'InternScience/Agents-A1',
            ],
            [
                '_id' => '6a350e917c8a230bec04a600',
                'id' => 'baidu/Unlimited-OCR',
                'likes' => 1922,
                'trendingScore' => 212,
                'private' => false,
                'downloads' => 1319683,
                'tags' => ['transformers', 'safetensors', 'unlimited-ocr', 'feature-extraction', 'baidu', 'vision-language', 'ocr', 'custom_code', 'image-text-to-text', 'multilingual', 'arxiv:2606.23050', 'license:mit', 'region:us'],
                'pipeline_tag' => 'feature-extraction',
                'library_name' => 'transformers',
                'createdAt' => '2026-06-19T09:40:33.000Z',
                'modelId' => 'baidu/Unlimited-OCR',
            ],
        ];
    }

    public function test_it_creates_a_subject_and_release_event_for_each_new_trending_model(): void
    {
        Http::fake([
            'huggingface.co/api/models*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchHuggingFaceTrending)->handle();

        $this->assertDatabaseCount('subjects', 5);
        $this->assertDatabaseCount('events', 5);

        $subject = Subject::where('external_url', 'https://huggingface.co/tencent/Hy3')->firstOrFail();
        $this->assertSame('tencent/Hy3', $subject->name);
        $this->assertSame(SubjectKind::Model, $subject->kind);
        $this->assertSame(6923, $subject->metric_value);
        $this->assertCount(1, $subject->metric_history);
        $this->assertSame(650, $subject->metric_history[0]['trending_score']);
        $this->assertSame('2026-07-02T13:23:54+00:00', $subject->first_seen_at->toIso8601String());

        $event = Event::where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame(EventType::Release, $event->type);
        $this->assertSame('https://huggingface.co/tencent/Hy3', $event->url);
        $this->assertSame('huggingface', $event->source);
        $this->assertSame('text-generation', $event->payload['pipeline_tag']);
    }

    public function test_it_updates_metrics_without_creating_a_duplicate_event_on_repeat_polls(): void
    {
        Http::fake([
            'huggingface.co/api/models*' => Http::response($this->sampleResponse()),
        ]);

        (new FetchHuggingFaceTrending)->handle();
        (new FetchHuggingFaceTrending)->handle();

        $this->assertDatabaseCount('subjects', 5);
        $this->assertDatabaseCount('events', 5);

        $subject = Subject::where('external_url', 'https://huggingface.co/tencent/Hy3')->firstOrFail();
        $this->assertCount(2, $subject->metric_history);
        $this->assertSame(6923, $subject->metric_value);
    }

    public function test_it_refreshes_metric_value_when_download_counts_change(): void
    {
        $first = $this->sampleResponse();
        $updated = $first;
        $updated[0]['downloads'] = 9999;
        $updated[0]['likes'] = 700;

        Http::fake([
            'huggingface.co/api/models*' => Http::sequence()
                ->push($first)
                ->push($updated),
        ]);

        (new FetchHuggingFaceTrending)->handle();
        (new FetchHuggingFaceTrending)->handle();

        $this->assertDatabaseCount('events', 5);

        $subject = Subject::where('external_url', 'https://huggingface.co/tencent/Hy3')->firstOrFail();
        $this->assertSame(9999, $subject->metric_value);
        $this->assertCount(2, $subject->metric_history);
    }

    public function test_it_caps_metric_history_at_thirty_snapshots(): void
    {
        Http::fake([
            'huggingface.co/api/models*' => Http::response($this->sampleResponse()),
        ]);

        for ($i = 0; $i < 35; $i++) {
            (new FetchHuggingFaceTrending)->handle();
        }

        $subject = Subject::where('external_url', 'https://huggingface.co/tencent/Hy3')->firstOrFail();
        $this->assertCount(30, $subject->metric_history);
    }
}
