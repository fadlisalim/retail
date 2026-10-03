<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleAdsOfflineConversions;
use App\Services\MonthlyRevenueReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan pendapatan bulanan (keuangan): rekap per bulan, rincian bulan, ekspor CSV. */
class ReportController extends Controller
{
    public function monthly(Request $request, MonthlyRevenueReport $report): View|StreamedResponse
    {
        $years = $report->availableYears();
        $year = (int) $request->integer('tahun') ?: (int) now()->format('Y');
        $year = in_array($year, $years, true) ? $year : $years[0];

        // Semua staf boleh memantau rekap; HPP & laba kotor hanya untuk yang boleh melihat harga modal.
        $showCost = $request->user()->can('payment.manage') || $request->user()->can('price.manage');

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $year, $showCost);
        }

        $data = $report->year($year);
        $month = (int) $request->integer('bulan');
        $detail = $month >= 1 && $month <= 12 ? $report->month($year, $month) : null;

        return view('admin.reports.monthly', $data + [
            'month' => $month,
            'detail' => $detail,
            'report' => $report,
            'showCost' => $showCost,
        ]);
    }

    /** CSV "Offline Conversion Import" Google Ads: pesanan lunas terverifikasi Keuangan dari klik iklan (gclid). */
    public function googleAds(Request $request, GoogleAdsOfflineConversions $export): StreamedResponse
    {
        $to = $request->filled('sampai') ? CarbonImmutable::parse($request->input('sampai')) : CarbonImmutable::today();
        $from = $request->filled('dari') ? CarbonImmutable::parse($request->input('dari')) : $to->subDays(30);
        $csv = $export->csv($from, $to);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'google-ads-konversi-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csv(MonthlyRevenueReport $report, int $year, bool $withCost): StreamedResponse
    {
        $rows = $report->csv($year, $withCost);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            foreach ($rows as $row) {
                fputcsv($out, $row, ';'); // Excel Indonesia memakai ; sebagai pemisah
            }
            fclose($out);
        }, 'laporan-pendapatan-'.$year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
