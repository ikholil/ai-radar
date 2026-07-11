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

        $sameHourDays = Event::where('occurred_at', '>=', now()->subDays(7))
            ->where('occurred_at', '<', now()->subHour())
            ->whereRaw("CAST(strftime('%H', occurred_at) AS INTEGER) = ?", [(int) now()->format('H')])
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
