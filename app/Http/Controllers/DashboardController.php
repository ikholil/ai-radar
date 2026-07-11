<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Subject;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'events' => Event::with('subject')->orderByDesc('occurred_at')->limit(50)->get(),
            'trending' => Subject::orderByDesc('metric_value')->limit(10)->get(),
            'newArrivals' => Subject::where('first_seen_at', '>=', now()->subDays(7))
                ->orderByDesc('first_seen_at')
                ->limit(8)
                ->get(),
            'pulse' => $this->pulseScore(),
        ]);
    }

    private function pulseScore(): array
    {
        $currentHourCount = Event::where('occurred_at', '>=', now()->subHour())->count();

        // Filtered in PHP rather than via a DB-specific hour-extraction
        // function (SQLite's strftime vs Postgres's EXTRACT) - a week of
        // events is small enough that this isn't a performance concern.
        $targetHour = (int) now()->format('H');

        $sameHourDays = Event::where('occurred_at', '>=', now()->subDays(7))
            ->where('occurred_at', '<', now()->subHour())
            ->get(['occurred_at'])
            ->filter(fn (Event $event) => (int) $event->occurred_at->format('H') === $targetHour)
            ->count();

        $baseline = $sameHourDays > 0 ? $sameHourDays / 7 : null;
        $percent = $baseline && $baseline > 0
            ? (int) round(($currentHourCount / $baseline) * 100)
            : null;

        return [
            'current' => $currentHourCount,
            'baseline' => $baseline,
            'percent' => $percent,
        ];
    }
}
