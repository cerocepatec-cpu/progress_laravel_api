<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
    .header img { max-height: 60px; margin-bottom: 5px; }
    .title { font-size: 20px; font-weight: bold; }
    .section { margin-bottom: 15px; }
    .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .table th, .table td { border: 1px solid #555; padding: 6px; text-align: left; }
    .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #777; }
</style>
</head>
<body>

<div class="header">
    @if($enterprise && $enterprise->logo_path)
        <img src="{{ public_path($enterprise->logo_path) }}" alt="Logo">
    @endif
    <div class="title">RAPPORT DE CLÔTURE DE CAISSE</div>
    @if($enterprise)
        <div>{{ $enterprise->name }} - {{ $enterprise->address }}</div>
        <div>Tel: {{ $enterprise->phone }} | Email: {{ $enterprise->email }}</div>
    @endif
    <div>Date d'impression : {{ $date }}</div>
</div>

<div class="section">
    <strong>Utilisateur :</strong> {{ $user->name }}<br>
    <strong>Caisse :</strong> {{ $fund->name }} - {{ $fund->description ?? '' }}<br>
    <strong>Devise :</strong> {{ $currency->abreviation }} ({{ $currency->name }})
</div>

<div class="section">
    <strong>Montant total :</strong> {{ number_format($closure->total_amount, 2, ',', ' ') }} {{ $currency->abreviation }}<br>
    <strong>Statut :</strong> {{ ucfirst($closure->status) }}<br>
    <strong>Date de clôture :</strong> {{ $closure->closed_at->format('d/m/Y H:i') }}<br>
    <strong>Note :</strong> {{ $closure->closure_note ?? 'Aucune note' }}
</div>

<div class="section">
    <h3>🧾 Détail des billets</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Valeur du billet</th>
                <th>Quantité</th>
                <th>Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($closure->billages as $value => $qty)
            <tr>
                <td>{{ number_format($value, 0, ',', ' ') }} {{ $currency->abreviation }}</td>
                <td>{{ $qty }}</td>
                <td>{{ number_format($value * $qty, 2, ',', ' ') }} {{ $currency->abreviation }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="footer">
    Impression générée automatiquement — {{ config('app.name') }}
</div>

</body>
</html>
