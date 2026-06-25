@php
    $reportLabels = [
        'daily'   => 'Quotidien',
        'weekly'  => 'Hebdomadaire',
        'monthly' => 'Mensuel',
    ];

    $reportKey = strtolower($reportType);
    $reportLabel = $reportLabels[$reportKey] ?? ucfirst($reportType);
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>WEKA AKIBA – Rapport financier</title>

    <style>
        /* ================= PAGE ================= */
        @page {
            margin: 40px 40px 90px 40px; /* bas réservé au footer */
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #0f172a;
        }

        h2, h3, h4 {
            margin-bottom: 8px;
            color: #020617;
        }

        p {
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f1f5f9;
            text-align: left;
            font-weight: bold;
            padding: 8px;
            border-bottom: 1px solid #cbd5f5;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .section {
            margin-top: 25px;
        }

        .muted {
            color: #475569;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            background-color: #e0f2fe;
            color: #0369a1;
        }

        /* ================= FOOTER ================= */
        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            font-size: 11px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ================= EN-TÊTE ================= --}}
    <h2>WEKA AKIBA</h2>
    <p class="muted">
        Sécurisé • Transparent • Fiable
    </p>

    <hr>

    {{-- ================= INFORMATIONS UTILISATEUR ================= --}}
    <p>
        <strong>Titulaire du compte :</strong>
        {{ $user->full_name ?? $user->user_name }}
    </p>

    <p>
        <strong>Identifiant utilisateur :</strong> {{ $user->uuid }}
        <br>
        <strong>Email :</strong> {{ $user->email }}
    </p>

    <p>
        <strong>Type de rapport :</strong>
        <span class="badge">{{ ($reportLabel) }}</span>
        <br>
        <strong>Date de génération :</strong> {{ $generatedAt }}
    </p>

    <hr>

    {{-- ================= INTRODUCTION ================= --}}
    <p>
        Cher(ère) <strong>{{ $user->full_name ?? $user->user_name }}</strong>,
    </p>

    <p>
        Veuillez trouver ci-dessous le résumé de votre
        <strong>rapport financier {{ strtoupper($reportLabel) }}</strong>.
        Ce document reflète l’état de vos comptes au moment de sa génération.
    </p>

    {{-- ================= RÉCAPITULATIF PAR DEVISE ================= --}}
    <div class="section">
        <h4>Récapitulatif des soldes par devise</h4>

        <table>
            <thead>
                <tr>
                    <th>Devise</th>
                    <th class="text-right">Solde total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($totals as $item)
                    <tr>
                        <td>{{ $item['currency'] }}</td>
                        <td class="text-right">
                            {{ number_format($item['sum'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ================= APERÇU DES COMPTES ================= --}}
    <div class="section">
        <h4>Aperçu des comptes</h4>

        <table>
            <thead>
                <tr>
                    <th>Numéro de compte</th>
                    <th>Devise</th>
                    <th class="text-right">Solde disponible</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td>{{ $account['account_number'] }}</td>
                        <td>{{ $account['currency'] }}</td>
                        <td class="text-right">
                            {{ number_format($account['available_balance'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ================= BONUS COLLECTEUR ================= --}}
    @if ($user->collector && !empty($collectorBonuses))
        <div class="section">
            <h4>Bonus collecteur (en attente)</h4>

            <table>
                <thead>
                    <tr>
                        <th>Devise</th>
                        <th class="text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($collectorBonuses as $bonus)
                        <tr>
                            <td>{{ $bonus['currency'] }}</td>
                            <td class="text-right">
                                {{ number_format($bonus['amount'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ================= NOTE DE SÉCURITÉ ================= --}}
    <div class="section">
        <p class="muted">
            Pour des raisons de sécurité, l’historique détaillé des transactions
            est disponible exclusivement dans votre application WEKA AKIBA.
        </p>
    </div>

    {{-- ================= PIED DE PAGE ================= --}}
    <div class="footer">
        <hr>

        <p>
            Ce document est confidentiel et destiné uniquement au titulaire du compte.
            Si vous n’êtes pas à l’origine de cette demande, veuillez contacter immédiatement
            notre service d’assistance.
        </p>

        <p>
            © {{ date('Y') }} WEKA AKIBA. Tous droits réservés.
        </p>
    </div>
    <script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(520, 820, "Page {PAGE_NUM} / {PAGE_COUNT}", null, 9, [100,100,100]);
    }
</script>
</body>
</html>
