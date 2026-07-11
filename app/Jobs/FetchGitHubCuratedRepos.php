<?php

namespace App\Jobs;

use App\Jobs\Concerns\TracksGitHubRepoStars;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchGitHubCuratedRepos implements ShouldQueue
{
    use Queueable, TracksGitHubRepoStars;

    public int $timeout = 30;

    public int $tries = 3;

    private const ENDPOINT = 'https://api.github.com/search/repositories';

    private const TOP_PER_ORG = 3;

    /**
     * Curated list of AI-relevant orgs/users. For each, the top starred
     * repos are tracked - not a fixed repo list, since orgs' flagship
     * projects shift over time and this avoids hand-picking wrong ones.
     */
    private const ORGS = [
        'microsoft', 'openai', 'anthropics', 'google', 'google-deepmind',
        'huggingface', 'langchain-ai', 'microsoftgraph', 'pytorch', 'tensorflow',
        'keras-team', 'jax-ml', 'vllm-project', 'unslothai', 'lmstudio-ai',
        'ollama', 'llamaindex', 'crewAIInc', 'mem0ai', 'open-webui',
        'browser-use', 'all-hands-ai', 'camel-ai', 'agno-agi', 'modelcontextprotocol',
        'continue-revolution', 'vercel', 'cloudflare', 'modal-labs', 'lightning-ai',
        'gradio-app', 'streamlit', 'bentoml', 'ray-project', 'mlflow',
        'dstackai', 'n8n-io', 'FlowiseAI', 'Comfy-Org', 'AUTOMATIC1111',
        'Stability-AI', 'deepseek-ai', 'QwenLM', 'InternLM', 'Alibaba-NLP',
        'bytedance', 'ByteDance-Seed', 'NVIDIA', 'IntelLabs', 'tinygrad',
    ];

    public function handle(): void
    {
        $seenIds = [];

        foreach (self::ORGS as $org) {
            $items = $this->githubClient()
                ->get(self::ENDPOINT, [
                    'q' => "org:{$org}",
                    'sort' => 'stars',
                    'order' => 'desc',
                    'per_page' => self::TOP_PER_ORG,
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
