<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; background:#f9fafb; padding:20px;">
  <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:24px;">
    
    <h2 style="color:#111827;">🔐 Nouvelle connexion détectée</h2>

    <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

    <p>
      Une nouvelle connexion à votre compte a été détectée avec les informations suivantes :
    </p>

    <table style="width:100%;font-size:14px;">
      <tr><td><strong>Date</strong></td><td>{{ now()->format('d/m/Y H:i') }}</td></tr>
      <tr><td><strong>Adresse IP</strong></td><td>{{ $context['ip'] }}</td></tr>
      <tr><td><strong>Appareil</strong></td><td>{{ $context['device'] }}</td></tr>
      <tr><td><strong>Navigateur</strong></td><td>{{ $context['browser'] }}</td></tr>
      <tr><td><strong>Localisation</strong></td><td>{{ $context['location'] }}</td></tr>
    </table>

    <p style="margin-top:20px;">
      Si cette connexion vous semble inhabituelle, nous vous recommandons de :
    </p>

    <ul>
      <li>Changer immédiatement votre mot de passe</li>
      <li>Révoquer les sessions actives</li>
      <li>Contacter le support</li>
    </ul>

    <p style="color:#6b7280;font-size:13px;">
      Si vous êtes à l’origine de cette connexion, vous pouvez ignorer cet email.
    </p>

    <hr style="margin:24px 0;">

    <p style="font-size:12px;color:#9ca3af;">
      Cet email a été envoyé automatiquement pour votre sécurité.
    </p>
  </div>
</body>
</html>
