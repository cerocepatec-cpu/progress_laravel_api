<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0" />
<title>Reçu de transaction - Weka Akiba</title>

<style>
  body {
    margin: 0;
    padding: 0;
    background: #f4f6f8;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1f2937;
  }

  .container {
    max-width: 580px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
  }

  .header {
    background: #111827;
    padding: 20px 24px;
    text-align: center;
    color: #f9fafb;
  }

  .header h1 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
  }

  .content {
    padding: 24px;
  }

  .title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
  }

  .summary-card {
    background: #f9fafb;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
  }

  .summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin: 6px 0;
  }

  .summary-row strong {
    font-weight: 600;
  }

  .separator {
    border-top: 1px solid #e5e7eb;
    margin: 18px 0;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }

  .table td {
    padding: 8px 4px;
    font-size: 14px;
    border-bottom: 1px solid #f1f5f9;
  }

  .footer {
    background: #f9fafb;
    padding: 16px 24px;
    text-align: center;
    border-top: 1px solid #e5e7eb;
    font-size: 12px;
    color: #6b7280;
  }
</style>

</head>
<body>

<div class="container">
  <!-- HEADER -->
  <div class="header">
    <h1>Reçu de transaction</h1>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <p class="title">Votre transaction a été effectuée avec succès.</p>

    <!-- SUMMARY CARD -->
    <div class="summary-card">
      <div class="summary-row">
        <span><strong>Montant :</strong></span>
        <span>{{ number_format($amount, 2, ',', ' ') }} FC</span>
      </div>

      <div class="summary-row">
        <span><strong>Frais :</strong></span>
        <span>{{ number_format($fees, 2, ',', ' ') }} FC</span>
      </div>

      <div class="summary-row">
        <span><strong>Date :</strong></span>
        <span>{{ $date }}</span>
      </div>

      @if(!empty($ref))
      <div class="summary-row">
        <span><strong>Référence :</strong></span>
        <span>{{ $ref }}</span>
      </div>
      @endif
    </div>

    <!-- DETAILS TABLE -->
    <p class="title">Détails de la transaction</p>

    <table class="table">
      <tr>
        <td><strong>Compte source</strong></td>
        <td>{{ $source }}</td>
      </tr>

      <tr>
        <td><strong>Compte bénéficiaire</strong></td>
        <td>{{ $beneficiary }}</td>
      </tr>

      <tr>
        <td><strong>Motif</strong></td>
        <td>{{ $motif }}</td>
      </tr>
    </table>

  </div>

  <!-- FOOTER -->
  <div class="footer">
    © {{ date('Y') }} Weka Akiba — Tous droits réservés.<br>
    Ceci est un email automatique, merci de ne pas y répondre.
  </div>

</div>

</body>
</html>
