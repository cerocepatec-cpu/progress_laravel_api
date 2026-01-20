<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; padding: 0; color: #333; }
        .container { padding: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .company-info { font-size: 14px; }
        .invoice-title { font-size: 20px; font-weight: bold; }
        .invoice-info { margin-top: 10px; }

        .client-info, .invoice-details { margin-bottom: 20px; }
        .section-title { font-weight: bold; margin-bottom: 5px; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }

        .totals { margin-top: 20px; width: 100%; }
        .totals td { padding: 5px; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .footer { margin-top: 60px; font-size: 11px; text-align: center; color: #777; }
    </style>
</head>
<body>
    <div class="container">

        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <strong>{{ $company['name'] }}</strong><br>
                {{ $company['address'] }}<br>
                Email: {{ $company['email'] }}<br>
                Téléphone: {{ $company['phone'] }}
            </div>

            <div class="invoice-title">
                FACTURE<br>
                <div class="invoice-info">
                    N°: {{ $invoice['invoice']['uuid'] }}<br>
                    Date: {{ \Carbon\Carbon::parse($invoice['invoice']['date_operation'])->format('d/m/Y') }}
                </div>
            </div>
        </div>

        <!-- Client -->
        <div class="client-info">
            <div class="section-title">Facturé à :</div>
            {{ $invoice['invoice']['customer_name'] }}<br>
            {{ $invoice['invoice']['address'] }}<br>
            Email: {{ $invoice['invoice']['mail'] }}
        </div>

        <!-- Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Désignation</th>
                    <th>Déscription</th>
                    <th>Quantité</th>
                    <th>Prix Unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice['details'] as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->service_name }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->price, 2, ',', ' ') }} {{$item->abreviation}}</td>
                        <td>{{ number_format($item->quantity * $item->price, 2, ',', ' ') }} {{$item->abreviation}} </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <table class="totals">
            <tr>
                <td class="right bold">Sous-total :</td>
                <td class="right">{{ number_format($invoice['invoice']['total'], 2, ',', ' ') }} {{$invoice['invoice']['abreviation']}}</td>
            </tr>
            <tr>
                <td class="right bold">TVA (16%) :</td>
                <td class="right">{{ number_format($invoice['invoice']['vat_percent'], 2, ',', ' ') }} {{$invoice['invoice']['abreviation']}}</td>
            </tr>
            <tr>
                <td class="right bold">Total à payer :</td>
                <td class="right bold">{{ number_format($invoice['invoice']['netToPay'], 2, ',', ' ') }} {{$invoice['invoice']['abreviation']}}</td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            Merci pour votre confiance !<br>
            Cette facture est générée automatiquement et n’a pas besoin de signature.
        </div>
    </div>
</body>
</html>
