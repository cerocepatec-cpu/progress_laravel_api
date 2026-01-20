<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>

    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #eef1f5;
            padding: 30px;
            margin: 0;
            color: #333;
        }

        .wrapper {
            max-width: 650px;
            margin: auto;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
            border: 1px solid #e4e7eb;
        }

        .header {
            background: linear-gradient(135deg, #ffb400, #ff9100);
            text-align: center;
            padding: 30px 20px;
            color: #fff;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 0.5px;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 25px 30px;
        }

        .title-block {
            margin-bottom: 25px;
            text-align: center;
        }

        .title-block h2 {
            margin: 0;
            font-size: 20px;
            color: #444;
            font-weight: 600;
        }

        .summary-box {
            background: #f9fafc;
            border: 1px solid #e1e4e8;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .row {
            margin-bottom: 15px;
        }

        .label {
            font-weight: bold;
            font-size: 14px;
            color: #555;
            display: block;
        }

        .value {
            font-size: 15px;
            color: #222;
            margin-top: 3px;
        }

        .highlight {
            color: #ff9800;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            padding: 20px 15px;
            background: #fafafa;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #888;
        }

        .footer span {
            display: block;
            margin-top: 5px;
            font-size: 11px;
        }

    </style>
</head>

<body>

    <div class="wrapper">

        <!-- HEADER -->
        <div class="header">
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>

        <!-- BODY -->
        <div class="content">

            <div class="title-block">
                <h2>Détails de la Transaction</h2>
            </div>
<div class="summary-box">

    <div class="row">
                    <span class="label">Compte Source :</span>
                    <span class="value">{{ $source_account ?? 'N/A' }}</span>
                </div>

                <div class="row">
                    <span class="label">Compte Bénéficiaire :</span>
                    <span class="value">{{ $beneficiary_account ?? 'N/A' }}</span>
                </div>

                <hr style="border:0;border-top:1px solid #ddd;margin:18px 0;">

                <div class="row">
                    <span class="label">ID Transaction :</span>
                    <span class="value">{{ $uuid ?? 'N/A' }}</span>
                </div>

                <div class="row">
                    <span class="label">Montant :</span>
                    <span class="value">{{ number_format($amount ?? 0, 2) }} {{ $currency ?? '' }}</span>
                </div>

                <div class="row">
                    <span class="label">Frais appliqués :</span>
                    <span class="value">{{ number_format($fees ?? 0, 2) }} {{ $currency ?? '' }}</span>
                </div>

                <div class="row">
                    <span class="label">Solde avant :</span>
                    <span class="value">{{ number_format($before ?? 0, 2) }} {{ $currency ?? '' }}</span>
                </div>

                <div class="row">
                    <span class="label">Solde après :</span>
                    <span class="value highlight">{{ number_format($after ?? 0, 2) }} {{ $currency ?? '' }}</span>
                </div>

                <div class="row">
                    <span class="label">Motif :</span>
                    <span class="value">{{ $motif ?? 'N/A' }}</span>
                </div>

                <div class="row">
                    <span class="label">Date :</span>
                    <span class="value">{{ ($date ?? now())->format('d/m/Y H:i') }}</span>
                </div>

</div>

        </div>

        <!-- FOOTER -->
        <div class="footer">
            WEKA AKIBA SYSTEM  
            <span>Notification automatique, ne pas répondre à cet email.</span>
        </div>

    </div>

</body>
</html>
