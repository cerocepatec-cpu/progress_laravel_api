<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\UserReportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use App\Services\Reports\ReportService;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Reports\PdfReportService;
use App\Services\BonusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUserReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    protected int $userId;
    protected string $reportType;

    public function __construct(int $userId, string $reportType)
    {
        $this->userId = $userId;
        $this->reportType = $reportType;
    }

    public function handle(): void
    {
        $pdfPath = null;

        try {

            $user = User::with('preferences')->findOrFail($this->userId);

            // 🔐 Sécurité minimale
            if (!$user->email) {
                Log::warning("Report skipped: user {$user->id} has no email");
                return;
            }

            // 🕒 Date génération (timezone user)
            $generatedAt = ReportService::generatedAt($user);

            // 📊 Données principales
            $totalsByCurrency = ReportService::totalsByCurrency($user);
            $accountsSnapshot = ReportService::accountsSnapshot($user->id);

            if (empty($accountsSnapshot)) {
                Log::warning("Report skipped: no accounts for user {$user->id}");
                return;
            }

            /**
             * 🎁 BONUS COLLECTEUR (NOUVELLE LOGIQUE)
             */
            $collectorBonuses = [];

            if ($user->collector) {
                try {
                    $collectorBonuses = app(BonusService::class)
                        ->getPendingByCurrency($user->id)
                        ->map(function ($row) {
                            return [
                                'currency' => $row->currency->abreviation ?? 'N/A',
                                'amount'  => (float) $row->total,
                            ];
                        })
                        ->values()
                        ->toArray();
                } catch (Throwable $e) {
                    // ⚠️ Les bonus ne doivent jamais bloquer le report
                    Log::warning('Collector bonus retrieval failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 📄 Génération PDF
            $pdfPath = PdfReportService::generate(
                $user,
                $totalsByCurrency,
                $accountsSnapshot,
                $this->reportType,
                $collectorBonuses
            );

            // ✉️ Envoi email
            Mail::to($user->email)->send(
                new UserReportMail(
                    user: $user,
                    totals: $totalsByCurrency,
                    accountsSnapshot: $accountsSnapshot,
                    reportType: ucfirst($this->reportType),
                    pdfPath: $pdfPath,
                    generatedAt: $generatedAt->format('Y-m-d H:i'),
                    collectorBonuses: $collectorBonuses
                )
            );

           // 🕒 Heure locale lisible
            $time = $generatedAt->format('H:i');

            // 🧠 Message selon le type de rapport
            $reportLabel = match ($this->reportType) {
                'daily'   => 'quotidien',
                'weekly'  => 'hebdomadaire',
                'monthly' => 'mensuel',
                default   => 'périodique',
            };

            $message = match ($this->reportType) {
                'daily' =>
                    "Votre rapport quotidien est prêt. Généré à {$time}, il vous permet de suivre l’activité et les soldes de vos comptes.",

                'weekly' =>
                    "Votre rapport hebdomadaire est disponible. Généré à {$time}, il récapitule l’activité et l’évolution de vos comptes cette semaine.",

                'monthly' =>
                    "Votre rapport mensuel est disponible. Généré à {$time}, il présente le résumé complet de vos comptes pour le mois écoulé.",

                default =>
                    "Votre rapport est disponible. Généré à {$time}.",
            };

            // 🔔 Notification temps réel utilisateur
            event(new \App\Events\UserRealtimeNotification(
                $user->id,
                "Rapport {$reportLabel} disponible",
                $message,
                'success'
            ));

            Log::info("Report sent ({$this->reportType}) to user {$user->id}");

        } catch (Throwable $e) {

            Log::error('SendUserReportJob failed', [
                'user_id' => $this->userId,
                'report_type' => $this->reportType,
                'error' => $e->getMessage(),
            ]);

            throw $e;

        } finally {

            // 🧹 Nettoyage PDF
            if ($pdfPath && file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('SendUserReportJob permanently failed', [
            'user_id' => $this->userId,
            'report_type' => $this->reportType,
            'error' => $exception->getMessage(),
        ]);
    }
}
