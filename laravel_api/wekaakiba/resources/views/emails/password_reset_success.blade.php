
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe réinitialisé</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; background-color: #f5f7fa; color: #333; margin:0; padding:0; }
        .container { max-width: 600px; margin: 50px auto; background: #fff; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #0f172a; color: #fff; text-align: center; padding: 30px; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .content h2 { color: #0f172a; }
        .content p { line-height: 1.6; font-size: 16px; }
        .footer { background: #e5e7eb; text-align: center; padding: 20px; font-size: 12px; color: #555; }
        .btn { display:inline-block; padding: 12px 20px; background:#2563eb; color:#fff; border-radius:5px; text-decoration:none; margin-top:20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sécurité et confiance – {{ $enterprise->name ?? 'Votre entreprise' }}</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ $user->full_name ?? $user->email }},</h2>
            <p>Votre mot de passe a été réinitialisé avec succès. Vous pouvez désormais vous connecter avec votre nouveau mot de passe.</p>
            <p>Si vous n’êtes pas à l’origine de cette modification, veuillez contacter notre support immédiatement.</p>
            <a href="{{ url('/login') }}" class="btn">Se connecter maintenant</a>

            @if(!empty($enterprise->description))
            <p style="margin-top: 25px; color: #555; font-style: italic;">
                {{ $enterprise->description }}
            </p>
            @endif
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} WEKA AKIBA. Tous droits réservés.
        </div>
    </div>
</body>
</html>