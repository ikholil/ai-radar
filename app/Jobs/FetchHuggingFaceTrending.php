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

class FetchHuggingFaceTrending implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    private const ENDPOINT = 'https://huggingface.co/api/models';

    private const HISTORY_LIMIT = 30;

    public function handle(): void
    {
        // huggingface.co rejects sort=trending; the default ordering is
        // already trendingScore descending, so no sort param is passed.
        $models = Http::timeout($this->timeout)
            ->get(self::ENDPOINT, ['limit' => 20])
            ->throw()
            ->json();

        foreach ($models as $model) {
            $this->processModel($model);
        }
    }

    private function processModel(array $model): void
    {
        $externalUrl = 'https://huggingface.co/'.$model['id'];
        $downloads = $model['downloads'] ?? 0;

        $subject = Subject::where('external_url', $externalUrl)->first();

        if ($subject) {
            $this->recordSnapshot($subject, $model, $downloads);

            return;
        }

        $subject = Subject::create([
            'name' => $model['id'],
            'kind' => SubjectKind::Model,
            'external_url' => $externalUrl,
            'metric_value' => $downloads,
            'metric_history' => [$this->snapshot($model, $downloads)],
            'first_seen_at' => Carbon::parse($model['createdAt'] ?? now()),
        ]);

        Event::create([
            'type' => EventType::Release,
            'subject_id' => $subject->id,
            'title' => "{$model['id']} is trending on Hugging Face",
            'url' => $externalUrl,
            'summary' => $model['pipeline_tag'] ?? null,
            'source' => 'huggingface',
            'payload' => $model,
            'occurred_at' => Carbon::parse($model['createdAt'] ?? now()),
        ]);
    }

    private function recordSnapshot(Subject $subject, array $model, int $downloads): void
    {
        $history = [...($subject->metric_history ?? []), $this->snapshot($model, $downloads)];

        $subject->update([
            'metric_value' => $downloads,
            'metric_history' => array_slice($history, -self::HISTORY_LIMIT),
        ]);
    }

    private function snapshot(array $model, int $downloads): array
    {
        return [
            'downloads' => $downloads,
            'likes' => $model['likes'] ?? 0,
            'trending_score' => $model['trendingScore'] ?? 0,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
