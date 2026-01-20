<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 7px;
        margin: 0;
        padding: 0;
        color: #1a1a1a;
    }

    .container {
        width: 100%;
        padding: 4px 6px;
        background-color: #fff;
    }

    /* HEADER */
    .header {
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #2c3e50;
        padding-bottom: 2px;
        margin-bottom: 3px;
    }
    .header-left {
        font-size: 6.5px;
        line-height: 1.2;
    }
    .header-right {
        text-align: right;
        font-size: 6.5px;
        line-height: 1.2;
    }

    /* TITLE */
    .title {
        text-align: center;
        font-weight: bold;
        font-size: 9px;
        margin: 2px 0 3px 0;
        border-bottom: 1px dashed #2c3e50;
        padding-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* SUMMARY BLOCK */
    .summary {
        margin-top: 2px;
        font-size: 7px;
        border-left: 1px solid #2c3e50;
        border-right: 1px solid #2c3e50;
        border-top: 1px solid #2c3e50;
        border-bottom: 1px solid #2c3e50;
        padding: 2px 4px;
        background-color: #f7f9fc;
    }
    .summary div {
        display: flex;
        justify-content: space-between;
        padding: 1px 0;
        border-bottom: 1px dotted #2c3e50;
    }
    .summary div:last-child { border-bottom: none; }
    .summary .label {
        font-weight: bold;
        color: #2c3e50;
        margin-right: 4px; /* espace avant les deux-points */
    }

    /* STATUS BADGE */
    .status-badge {
        display: inline-block;
        font-size: 6px;
        padding: 1px 3px;
        border-radius: 3px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .status-pending { background-color: #f9ed69; color: #856404; }
    .status-validated { background-color: #d4edda; color: #155724; }
    .status-rejected { background-color: #f8d7da; color: #721c24; }

    /* TOTAL */
    .total {
        font-weight: bold;
        font-size: 8px;
        border-top: 1px solid #2c3e50;
        border-bottom: 1px solid #2c3e50;
        padding: 2px 0;
        margin: 2px 0;
        text-align: center;
        background-color: #e8f1f8;
        letter-spacing: 0.5px;
    }

    /* FOOTER */
    .footer {
        text-align: center;
        font-size: 6px;
        margin-top: 3px;
        border-top: 1px dashed #2c3e50;
        padding-top: 1px;
        color: #555;
    }

    /* ICONS */
    .icon { font-weight: bold; color: #2c3e50; margin-right: 2px; }

</style>
</head>
<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            @if($enterprise)
                {{ $enterprise->name }}<br>
                📍 {{ $enterprise->adresse }}<br>
                📞 {{ $enterprise->phone }} | ✉ {{ $enterprise->mail }}
            @endif
        </div>
        <div class="header-right">
            Ticket #{{ $closure->id }}<br>
            {{ $date }}<br>
            Statut : 
            <span class="status-badge 
                @if($closure->status==='pending') status-pending 
                @elseif($closure->status==='validated') status-validated 
                @elseif($closure->status==='rejected') status-rejected 
                @endif">
                {{ ucfirst($closure->status) }}
            </span>
        </div>
    </div>

    <!-- TITLE -->
    <div class="title">Clôture de Caisse</div>

    <!-- SUMMARY -->
    <div class="summary">
        <div><span class="label">👤 Utilisateur&nbsp;&nbsp;:</span> &nbsp;&nbsp;<span>{{ $user->name }}</span></div>
        <div><span class="label">🏦 Caisse&nbsp;&nbsp;:</span> &nbsp;&nbsp;<span>{{ $fund->description ?? $fund->name }}</span></div>
        <div><span class="label">💰 Devise&nbsp;&nbsp;:</span> &nbsp;&nbsp;<span>{{ $currency->abreviation }}</span></div>
        <div><span class="label">📅 Date&nbsp;&nbsp;:</span> &nbsp;&nbsp;<span>{{ $closure->closed_at->format('d/m/Y H:i') }}</span></div>
        <div><span class="label">📝 Note&nbsp;&nbsp;:</span> &nbsp;&nbsp;<span>{{ $closure->closure_note ?? '-' }}</span></div>
    </div>

    <!-- TOTAL -->
    <div class="total">
        💵 Total : {{ number_format($closure->total_amount, 2, ',', ' ') }} {{ $currency->abreviation }}
    </div>

    <!-- FOOTER -->
    <div class="footer">
        Impression automatique — {{ $enterprise->name ?? '---' }}<br>
        Merci de votre confiance.
    </div>

</div>

</body>
</html>
