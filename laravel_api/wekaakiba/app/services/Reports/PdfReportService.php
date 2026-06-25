<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PdfReportService
{
    /**
     * Génère le PDF et retourne le chemin
     */
    public static function generate(
        User $user,
        array $totalsByCurrency,
        array $accountsSnapshot,
        string $reportType // daily | weekly | monthly
    ): string {

        $generatedAt = ReportService::generatedAt($user);

        $data = [
            'company' => 'WEKA AKIBA',
            'user' => $user,
            'generatedAt' => $generatedAt,
            'totals' => $totalsByCurrency,
            'accounts' => $accountsSnapshot,
            'reportType' => ucfirst($reportType),
        ];

        $fileName = sprintf(
            'report_%d_%s_%s.pdf',
            $user->id,
            $reportType,
            $generatedAt->format('Ymd')
        );

        $path = "reports/{$reportType}/{$fileName}";

        $pdf = Pdf::loadView('reports.user_report', $data)
            ->setPaper('A4', 'portrait');

        Storage::disk('local')->put($path, $pdf->output());

        return storage_path("app/{$path}");
    }
}
