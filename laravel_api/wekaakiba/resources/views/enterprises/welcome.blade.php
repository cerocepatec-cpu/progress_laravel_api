<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bienvenue - {{ $user['name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #333;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header img {
            height: 70px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .content {
            line-height: 1.7;
        }

        .credentials {
            background: #f2f2f2;
            padding: 10px;
            margin-top: 15px;
            border-radius: 6px;
            width: fit-content;
        }

        .footer {
            margin-top: 50px;
            font-style: italic;
        }
    </style>
</head>
<body>

    <div class="header">
        @if(isset($company['logo']) && file_exists($company['logo']))
            <img src="{{ $company['logo'] }}" alt="Logo">
        @else
            <h2>{{ $company['name'] }}</h2>
        @endif
    </div>

    <div class="title">Bonjour {{ $user['name'] }} !</div>

    <div class="content">
        <p>
            Nous sommes ravis de vous accueillir sur la plateforme <strong>{{ $company['name'] }}</strong> avec votre entreprise <strong>{{ $user['entreprise'] }}</strong> !
        </p>
        <p>
            Votre inscription marque le début d'une expérience enrichissante et nous sommes impatients de vous accompagner dans votre parcours. Notre équipe est à votre disposition pour vous aider à tirer le meilleur parti de toutes les fonctionnalités que nous offrons.
        </p>
        <p>
            N’hésitez pas à explorer notre plateforme et à nous contacter à <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a> si vous avez des questions. Ensemble, faisons de cette aventure un véritable succès !
        </p>

        <div class="credentials">
            <p><strong>Vos identifiants par défaut sont :</strong></p>
            <p><strong>Nom utilisateur :</strong> {{ $user['name'] }}</p>
            <p><strong>Mot de passe :</strong> {{ $user['password'] }}</p>
        </div>

        <p class="footer">
            NB : Qu’il vous plaise de changer ces informations le plus tôt possible pour éviter toute sorte de contrefaçon !
        </p>

        <p class="footer">
            Encore une fois, bienvenue parmi nous !
        </p>

        <p class="footer">
            Cordialement,<br>
            L’équipe {{ $company['name'] }}
        </p>
    </div>

</body>
</html>
