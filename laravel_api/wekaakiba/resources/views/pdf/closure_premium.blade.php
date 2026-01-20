<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #1a1a1a;
        margin: 0;
        background-color: #fff;
    }

    /* === HEADER COMPACT === */
    .header-banner {
        background: #0a5275;
        color: #fff;
        padding: 10px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .enterprise-info {
        line-height: 1.4;
        font-size: 11.5px;
    }

    .enterprise-name {
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .meta-info {
        text-align: right;
        font-size: 11px;
        line-height: 1.5;
    }

    /* === TITLE === */
    .title {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        color: #0a5275;
        margin: 18px 0 10px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }

    /* === SUMMARY === */
    .summary {
        margin: 0 25px 15px;
        background: linear-gradient(135deg, #eaf5fb, #f9fcff);
        border: 1px solid #c6d9e3;
        border-radius: 6px;
        padding: 12px 16px;
    }

    .summary h3 {
        margin: 0 0 8px;
        color: #0a5275;
        font-size: 14px;
        text-transform: uppercase;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .summary-table td {
        padding: 5px 8px;
        border-bottom: 1px dashed #c9d6de;
    }

    .summary-table tr:last-child td {
        border-bottom: none;
    }

    .summary-label {
        color: #333;
        font-weight: 600;
    }

    .summary-value {
        text-align: right;
        font-weight: bold;
        color: #0a5275;
    }

    /* === SECTION === */
    .section {
        margin: 10px 25px;
        padding: 10px 15px;
        border: 1px solid #d9e0e6;
        border-radius: 6px;
        background-color: #f9fafc;
    }

    .section strong {
        color: #0a5275;
    }

    /* === TABLE === */
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        font-size: 11.5px;
    }

    .table th {
        background-color: #0a5275;
        color: #fff;
        padding: 7px;
        text-align: left;
        border: 1px solid #0a5275;
        font-size: 11px;
        text-transform: uppercase;
    }

    .table td {
        border: 1px solid #c8d2dc;
        padding: 5px 7px;
    }

    .table tr:nth-child(even) {
        background-color: #f3f6f9;
    }

    /* === FOOTER === */
    .footer {
        text-align: center;
        margin-top: 25px;
        padding: 8px 0;
        font-size: 10px;
        color: #777;
        border-top: 1px solid #ddd;
    }
</style>
</head>
<body>

{{-- HEADER --}}
<div class="header-banner">
    <div class="enterprise-info">
        @if($enterprise)
            <div class="enterprise-name">{{ $enterprise->name }}</div>
            <div>{{ $enterprise->adresse }}</div>
            <div>Tél : {{ $enterprise->phone }}</div>
            <div>Email : {{ $enterprise->mail }}</div>
        @endif
    </div>

    <div class="meta-info">
        <div><strong>RAPPORT #{{ $closure->id }}</strong></div>
        <div>Imprimé le : {{ $date }}</div>
        <div>Par : {{ $connected->name }}</div>
        <div>Statut : <b>{{ ucfirst($closure->status) }}</b></div>
    </div>
</div>

{{-- TITRE --}}
<div class="title">Rapport de clôture de caisse</div>

{{-- RÉSUMÉ CLÔTURE --}}
<div class="summary">
    <h3>Résumé de la clôture</h3>
    <table class="summary-table">
        <tr>
            <td class="summary-label">Montant total :</td>
            <td class="summary-value">{{ number_format($closure->total_amount, 2, ',', ' ') }} {{ $currency->abreviation }}</td>
        </tr>
        <tr>
            <td class="summary-label">Devise :</td>
            <td class="summary-value">{{ $currency->abreviation }} ({{ $currency->name }})</td>
        </tr>
        <tr>
            <td class="summary-label">Utilisateur :</td>
            <td class="summary-value">{{ $user->name }}</td>
        </tr>
        <tr>
            <td class="summary-label">Caisse :</td>
            <td class="summary-value">{{ $fund->description ?? $fund->name }}</td>
        </tr>
        <tr>
            <td class="summary-label">Date de clôture :</td>
            <td class="summary-value">{{ $closure->closed_at->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

{{-- DÉTAILS DES BILLETS --}}
<div class="section">
    <h3 style="color:#0a5275;margin-bottom:8px;">🧾 Détail des billets</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Valeur du billet</th>
                <th>Quantité</th>
                <th>Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($closure->billages))
                @foreach($closure->billages as $bill)
                <tr>
                    <td>{{ number_format($bill['nominal'] ?? 0, 0, ',', ' ') }} {{ $currency->abreviation }}</td>
                    <td>{{ $bill['quantity'] ?? 0 }}</td>
                    <td>{{ number_format(($bill['nominal'] ?? 0) * ($bill['quantity'] ?? 0), 2, ',', ' ') }} {{ $currency->abreviation }}</td>
                </tr>
                @endforeach
            @else
                <tr><td colspan="3" style="text-align:center;">Aucun détail de billets</td></tr>
            @endif
        </tbody>
    </table>
</div>

{{-- NOTE --}}
<div class="section">
    <strong>Note :</strong> {{ $closure->closure_note ?? 'Aucune note' }}
</div>

{{-- FOOTER --}}
<div class="footer">
    Rapport généré automatiquement — {{ $enterprise->name ?? '---' }} © {{ date('Y') }}<br>
    <small>Confidentiel — Ne pas distribuer sans autorisation</small>
</div>

</body>
</html>
