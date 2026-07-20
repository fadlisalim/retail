<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantDailyStat;
use App\Models\AssistantDailyTerm;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin dashboard for the CS assistant: volume stats (6-month rollups) and the
 * recent transcript log (short retention). Read-only.
 */
class AssistantLogController extends Controller
{
    public function index(Request $request): View
    {
        $since30 = now()->subDays(30)->startOfDay();

        // Headline totals over the last 30 days (from the rollups).
        $recentStats = AssistantDailyStat::whereDate('day', '>=', $since30->toDateString())->get();
        $summary = [
            'messages' => (int) $recentStats->sum('messages'),
            'answered' => (int) $recentStats->sum('answered'),
            'fallbacks' => (int) $recentStats->sum('fallbacks'),
            'sessions' => (int) $recentStats->sum('sessions'),
        ];
        $summary['answered_rate'] = $summary['messages'] > 0
            ? round($summary['answered'] / $summary['messages'] * 100)
            : 0;

        // Daily volume trend for a simple bar chart (whatever retention holds).
        $trend = AssistantDailyStat::orderBy('day')->get(['day', 'messages', 'answered', 'fallbacks'])
            ->map(fn (AssistantDailyStat $s) => [
                'day' => $s->day->format('d M'),
                'messages' => (int) $s->messages,
                'fallbacks' => (int) $s->fallbacks,
            ]);
        $trendMax = max(1, (int) $trend->max('messages'));

        // Top keywords & products over the full (6-month) stats retention.
        $topKeywords = AssistantDailyTerm::where('type', 'keyword')
            ->selectRaw('term, SUM(count) as total')
            ->groupBy('term')->orderByDesc('total')->limit(15)->get();

        $topProducts = AssistantDailyTerm::where('type', 'product')
            ->selectRaw('term, MAX(label) as label, SUM(count) as total')
            ->groupBy('term')->orderByDesc('total')->limit(15)->get();

        // Recent transcripts (short retention). Optional filter: only fallbacks.
        $query = AssistantConversation::latest();
        if ($request->input('filter') === 'fallback') {
            $query->where('answered', false);
        }
        $conversations = $query->paginate(25)->withQueryString();

        $logRetention = (int) config('services.anthropic.log_retention_days', 30);
        $statsRetention = (int) config('services.anthropic.stats_retention_days', 180);

        return view('admin.assistant', compact(
            'summary', 'trend', 'trendMax', 'topKeywords', 'topProducts',
            'conversations', 'logRetention', 'statsRetention',
        ));
    }
}
