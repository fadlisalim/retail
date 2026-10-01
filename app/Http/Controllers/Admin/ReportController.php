<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonthlyRevenueReport;
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

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $year);
        }

        $data = $report->year($year);
        $month = (int) $request->integer('bulan');
        $detail = $month >= 1 && $month <= 12 ? $report->month($year, $month) : null;

        return view('admin.reports.monthly', $data + [
            'month' => $month,
            'detail' => $detail,
            'report' => $report,
        ]);
    }

    private function csv(MonthlyRevenueReport $report, int $year): StreamedResponse
    {
        $rows = $report->csv($year);

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
