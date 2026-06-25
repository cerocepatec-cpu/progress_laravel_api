<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public array $totals;
    public array $accountsSnapshot;
    public array $collectorBonuses;
    public string $reportType;
    public string $pdfPath;
    public string $generatedAt;

    public function __construct(
        User $user,
        array $totals,
        array $accountsSnapshot,
        string $reportType,
        string $pdfPath,
        string $generatedAt,
        array $collectorBonuses = []
    ) {
        $this->user = $user;
        $this->totals = $totals;
        $this->accountsSnapshot = $accountsSnapshot;
        $this->reportType = $reportType;
        $this->pdfPath = $pdfPath;
        $this->generatedAt = $generatedAt;
        $this->collectorBonuses = $collectorBonuses;
    }

    public function build()
    {
        return $this
            ->subject("WEKA AKIBA – {$this->reportType} Rapport financier")
            ->view('reports.user_report')
            ->with([
                'user' => $this->user,
                'totals' => $this->totals,
                'accounts' => $this->accountsSnapshot,
                'collectorBonuses' => $this->collectorBonuses,
                'reportType' => $this->reportType,
                'generatedAt' => $this->generatedAt,
            ])
            ->attach($this->pdfPath, [
                'as' => "WEKA_AKIBA_{$this->reportType}_Report.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
