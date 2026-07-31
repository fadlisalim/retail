<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Traffic report: where visitors come from (Meta Ads, Instagram, Google,
 * WhatsApp, direct…), which campaigns bring them, and what they land on.
 * Built on site_visits (one row per session, first-touch), kept ~3 months.
 */
class TrafficController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->integer('hari', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        $base = fn () => SiteVisit::where('created_at', '>=', $since);

        $sessions = $base()->count();
        $pageViews = (int) $base()->sum('page_views');

        $bySource = $base()
            ->selectRaw('source, COUNT(*) as sessions, SUM(page_views) as views')
            ->groupBy('source')
            ->orderByDesc('sessions')
            ->get();

        $byCampaign = $base()
            ->whereNotNull('campaign')
            ->selectRaw('campaign, content, source, COUNT(*) as sessions')
            ->groupBy('campaign', 'content', 'source')
            ->orderByDesc('sessions')
            ->limit(20)
            ->get();

        $byLanding = $base()
            ->selectRaw('landing_path, COUNT(*) as sessions')
            ->groupBy('landing_path')
            ->orderByDesc('sessions')
            ->limit(15)
            ->get();

        $byReferrer = $base()
            ->whereNotNull('referrer_host')
            ->selectRaw('referrer_host, COUNT(*) as sessions')
            ->groupBy('referrer_host')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get();

        // Daily trend, zero-filled so gaps stay visible.
        $daily = $base()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as sessions')
            ->groupBy('day')
            ->pluck('sessions', 'day');
        $trend = collect(range($days - 1, 0))->map(function (int $back) use ($daily) {
            $day = now()->subDays($back)->toDateString();

            return ['day' => $day, 'sessions' => (int) ($daily[$day] ?? 0)];
        });

        $mobile = $base()->where('is_mobile', true)->count();

        return view('admin.traffic', [
            'days' => $days,
            'sessions' => $sessions,
            'pageViews' => $pageViews,
            'bySource' => $bySource,
            'byCampaign' => $byCampaign,
            'byLanding' => $byLanding,
            'byReferrer' => $byReferrer,
            'trend' => $trend,
            'mobileShare' => $sessions > 0 ? round($mobile / $sessions * 100) : 0,
            'retentionDays' => (int) config('rekasurya.traffic_retention_days', 90),
        ]);
    }
}
