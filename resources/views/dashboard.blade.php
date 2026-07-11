<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>AI Radar — Live Ecosystem Feed</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-950 font-mono text-slate-200 antialiased">
        <div x-data="{ filter: 'all' }" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-1 border-b border-white/10 pb-6">
                <h1 class="text-2xl font-bold tracking-widest text-emerald-400">AI RADAR</h1>
                <p class="text-sm text-slate-500">Live pulse of the AI ecosystem — models, repos, papers, and community activity.</p>
            </header>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                {{-- Main feed --}}
                <div class="lg:col-span-2">
                    {{-- Pulse score --}}
                    <div class="mb-6 rounded-lg border border-white/10 bg-white/[0.02] p-5">
                        <div class="text-xs uppercase tracking-widest text-slate-500">Pulse — last hour</div>
                        <div class="mt-1 flex items-baseline gap-3">
                            <span class="text-4xl font-bold text-emerald-400">{{ $pulse['current'] }}</span>
                            <span class="text-sm text-slate-500">events</span>
                            @if ($pulse['percent'] !== null)
                                <span class="ml-auto text-sm {{ $pulse['percent'] >= 100 ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $pulse['percent'] }}% of typical volume this hour
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Filter chips --}}
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach (['all' => 'All', 'models' => 'Models', 'papers' => 'Papers', 'tools' => 'Tools/Launches', 'community' => 'Community'] as $value => $label)
                            <button
                                @click="filter = '{{ $value }}'"
                                :class="filter === '{{ $value }}' ? 'bg-emerald-400/10 border-emerald-400/50 text-emerald-400' : 'border-white/10 text-slate-500 hover:text-slate-300'"
                                class="rounded-full border px-3 py-1 text-xs uppercase tracking-wide transition"
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    {{-- Feed --}}
                    <ul class="divide-y divide-white/5 rounded-lg border border-white/10">
                        @forelse ($events as $event)
                            <li
                                x-show="filter === 'all' || filter === '{{ $event->type->category() }}'"
                                class="flex items-start gap-3 p-4"
                            >
                                <span class="mt-0.5 shrink-0 rounded border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $event->type->colorClasses() }}">
                                    {{ $event->type->label() }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $event->url }}" target="_blank" rel="noopener" class="block truncate text-sm text-slate-100 hover:text-emerald-400">
                                        {{ $event->title }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                        <span>{{ $event->source }}</span>
                                        @if ($event->subject)
                                            <span>·</span>
                                            <span>{{ $event->subject->name }}</span>
                                        @endif
                                        <span>·</span>
                                        <span>{{ $event->occurred_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="p-6 text-center text-sm text-slate-500">No events yet — the fetcher jobs haven't run.</li>
                        @endforelse
                    </ul>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-8">
                    {{-- Trending leaderboard --}}
                    <div>
                        <h2 class="mb-3 text-xs uppercase tracking-widest text-slate-500">Trending</h2>
                        <ol class="space-y-1 rounded-lg border border-white/10 divide-y divide-white/5">
                            @forelse ($trending as $index => $subject)
                                <li class="flex items-center gap-3 p-3 text-sm">
                                    <span class="w-4 text-right text-slate-600">{{ $index + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-slate-100">{{ $subject->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $subject->kind->value }}</div>
                                    </div>
                                    <span class="shrink-0 text-xs text-emerald-400">{{ number_format($subject->metric_value ?? 0) }}</span>
                                </li>
                            @empty
                                <li class="p-4 text-center text-xs text-slate-500">Nothing tracked yet.</li>
                            @endforelse
                        </ol>
                    </div>

                    {{-- New arrivals --}}
                    <div>
                        <h2 class="mb-3 text-xs uppercase tracking-widest text-slate-500">New Arrivals</h2>
                        <ul class="space-y-1 rounded-lg border border-emerald-400/20 bg-emerald-400/[0.03] divide-y divide-white/5">
                            @forelse ($newArrivals as $subject)
                                <li class="p-3 text-sm">
                                    <div class="truncate text-slate-100">{{ $subject->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $subject->kind->value }} · first seen {{ $subject->first_seen_at->diffForHumans() }}</div>
                                </li>
                            @empty
                                <li class="p-4 text-center text-xs text-slate-500">Nothing new this week.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
