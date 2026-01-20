<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Transactions</title>
 <style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #2c3e50;
        margin: 0;
        padding: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #2c3e50;
    }

    th, td {
        border: 1px solid #444;
        padding: 6px 8px;
        vertical-align: middle;
    }

    th {
        background-color: #34495e;
        color: #ecf0f1;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-align: left;
        border: 1px solid #fff;
        border-bottom: 3px solid #fff;
    }

    tbody tr:nth-child(even) {
        background-color: #f7f9fa;
    }

    tbody tr:hover {
        background-color: #d1e7fd;
    }

    td.amount, td.date {
        text-align: right;
        font-feature-settings: "tnum";
        font-variant-numeric: tabular-nums;
    }

    td.id {
        font-weight: 600;
        color: #2980b9;
    }

    td.status {
        text-align: center;
        font-weight: 600;
        border-radius: 12px;
        color: white;
    }

    td.status.paid {
        background-color: #27ae60;
    }

    td.status.pending {
        background-color: #f39c12;
    }

    td.status.failed {
        background-color: #c0392b;
    }

    h2 {
        text-align: center;
        margin-bottom: 0;
        font-weight: 700;
        color: #34495e;
        letter-spacing: 0.05em;
    }

    .section {
        margin-bottom: 15px;
    }

    .signature {
        margin-top: 50px;
        width: 300px;
        padding: 12px 16px;
        border: 1px solid #ccc;
        float: right;
        text-align: left;
        font-size: 13px;
        background-color: #f9f9f9;
        box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.08);
        border-radius: 6px;
    }

    .signature p {
        margin: 8px 0;
        font-style: italic;
        color: #34495e;
    }

</style>

</head>
<body>
    <!-- Titre principal -->
        <h2 style="text-align: center; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; color: #2c3e50;">
            <u>HISTORIQUE DES TRANSACTIONS</u>
        </h2>

        <!-- En-tête d'informations -->
        <div class="header" style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #333;">
            <table style="width: 100%;">
                <tr>
                    <!-- Infos entreprise et période -->
                    <td style="width: 60%;">
                        <h3 style="margin: 0; color: #34495e;">{{ $enterprise->name }}</h3>
                        <p style="margin: 4px 0;"><strong>Période :</strong> {{ $from }} au {{ $to }}</p>
                        <p style="margin: 4px 0;"><strong>Total transactions :</strong> {{ $transactions->count() }}</p>
                        <p style="margin: 4px 0;">
                            @foreach($subtotals as $currency => $amount)
                                <strong>Sous-total ({{ $currency }}):</strong> {{ number_format($amount, 2, ',', ' ') }}<br>
                            @endforeach
                        </p>
                    </td>

                    <!-- Infos utilisateur -->
                    <td style="width: 40%; text-align: left; vertical-align: top;">
                        <p style="margin: 0 0 4px;"><strong>Imprimé par : {{ $actualuser->full_name ?? $actualuser->user_name }}</strong></p>
                        <p style="margin: 0;">UUID : {{ $actualuser->uuid ?? '-' }}</p>
                        <p style="margin: 0;">Date : {{ now()->format('d/m/Y H:i') }}</p>
                    </td>
                </tr>
            </table>
        </div>

        @if($subtotals && $subtotals->count() > 0)
            <div class="section">
                <strong>Sous-totaux par devise :</strong>
                <ul>
                    @foreach($subtotals as $currency => $amount)
                        <li>{{ $currency }} : {{ number_format($amount, 2, ',', ' ') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table class="transaction-table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>#</th>
                    <th>Date</th>
                    <th>Faite par</th>
                    <th>Membre</th>
                    <th>Type</th>
                    <th>Montant</th>
                    <th>Compte</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $index => $transaction)
                <tr>
                    <td>{{ $index+1 }}</td>
                    <td class="id">{{ $transaction->uuid }}</td>
                    <td class="date">{{ \Carbon\Carbon::parse($transaction->done_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $transaction->done_by_fullname ?? '' }}</td>
                    <td>{{ $transaction->member_fullname ?? '' }}</td>
                    <td>{{ ucfirst($transaction->type) }}</td>
                    <td class="amount">{{ number_format($transaction->amount, 2, ',', ' ') }} {{ $transaction->currency ?? '' }}</td>
                    <td>{{ $transaction->memberaccount_name ?? '' }}</td>
                    <td>
                        {{ ucfirst($transaction->transaction_status ?? '-') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
       <div class="signature">
            <p><strong>Validé par :</strong>-----------------------------------------</p>
            <p><strong>Date :</strong>----------------------------------------------</p>
        </div>

</body>
</html>
