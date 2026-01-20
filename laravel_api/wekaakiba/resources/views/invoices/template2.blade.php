<!-- PDF::loadView('invoices.full-template', [
    $company = [
    'name' => 'CERO UZISHA',
    'address' => 'Boulevard Kanyamuhanga, Goma',
    'email' => 'contact@cerocepa.com',
    'phone' => '+243 893 875 245',
    'logo' => public_path('storage/logos/cero.png') // chemin absolu requis par DomPDF
],
    'invoice' => $invoiceArray // ton tableau complet avec keys ['invoice'] + ['details']
'signatory' => [
    'name' => 'Jean BAKALI',
    'title' => 'Directeur Administratif'
]
]); -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice['invoice']['uuid'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 40px; }
        .container { padding: 0 20px; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo img {
            height: 80px;
        }

        .company-info {
            text-align: right;
            font-size: 13px;
            line-height: 1.5;
        }

        .invoice-title {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
        }

        .invoice-info {
            font-size: 13px;
            margin-top: 5px;
        }

        .client-info {
            margin-bottom: 20px;
            font-size: 13px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
        }

        .totals {
            width: 100%;
            margin-top: 20px;
        }

        .totals td {
            padding: 6px;
        }

        .right { text-align: right; }
        .bold { font-weight: bold; }

        .footer {
            margin-top: 40px;
            font-size: 11px;
            text-align: center;
            color: #777;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Header with logo and company info -->
    <div class="header">
        <div class="logo">
            @if(isset($company['logo']) && file_exists($company['logo']))
                <img src="{{ $company['logo'] }}" alt="Logo entreprise">
            @else
                <strong>{{ $company['name'] }}</strong>
            @endif
        </div>
        <div class="company-info">
            <strong>{{ $company['name'] }}</strong><br>
            {{ $company['address'] }}<br>
            Email : {{ $company['email'] }}<br>
            Téléphone : {{ $company['phone'] }}
        </div>
    </div>

    <!-- Invoice Info -->
    <div class="invoice-title">
        FACTURE
        <div class="invoice-info">
            N° : {{ $invoice['invoice']['uuid'] }}<br>
            Date : {{ \Carbon\Carbon::parse($invoice['invoice']['date_operation'])->format('d/m/Y') }}
        </div>
    </div>

    <!-- Client -->
    <div class="client-info">
        <div class="section-title">Facturé à :</div>
        {{ $invoice['invoice']['customer_name'] }}<br>
        {{ $invoice['invoice']['address'] }}<br>
        Email : {{ $invoice['invoice']['mail'] }}
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Désignation</th>
                <th>Description</th>
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
                    <td>{{ number_format($item->price, 2, ',', ' ') }} {{ $item->abreviation }}</td>
                    <td>{{ number_format($item->quantity * $item->price, 2, ',', ' ') }} {{ $item->abreviation }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <table class="totals">
        <tr>
            <td class="right bold">Sous-total :</td>
            <td class="right">{{ number_format($invoice['invoice']['total'], 2, ',', ' ') }} {{ $invoice['invoice']['abreviation'] }}</td>
        </tr>
        <tr>
            <td class="right bold">TVA (16%) :</td>
            <td class="right">{{ number_format($invoice['invoice']['vat_percent'], 2, ',', ' ') }} {{ $invoice['invoice']['abreviation'] }}</td>
        </tr>
        <tr>
            <td class="right bold">Total à payer :</td>
            <td class="right bold">{{ number_format($invoice['invoice']['netToPay'], 2, ',', ' ') }} {{ $invoice['invoice']['abreviation'] }}</td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        Merci pour votre confiance !<br>
        Cette facture est générée automatiquement et n’a pas besoin de signature.
    </div>

</div>
    <!-- Payment Conditions -->
    <div style="margin-top: 40px; font-size: 12px;">
        <strong>Conditions de paiement :</strong><br>
        Paiement à effectuer dans un délai de 7 jours à compter de la date de la facture.<br>
        Tout retard entraînera des pénalités conformément aux conditions générales de vente.
    </div>

    <!-- Signature -->
    <div style="margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div></div>
        <div style="text-align: center;">
            <div style="margin-bottom: 50px;">Fait à Goma, le {{ \Carbon\Carbon::parse($invoice['invoice']['date_operation'])->format('d/m/Y') }}</div>
            <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;"></div>
            <div style="font-size: 12px;">{{ $signatory['title'] ?? 'Responsable administratif' }}</div>
            <div style="font-size: 11px;">{{ $signatory['name'] ?? '(Signature)' }}</div>
        </div>
    </div>

</body>
</html>

