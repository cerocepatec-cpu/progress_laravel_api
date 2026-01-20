<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Alerte de sécurité – Mot de passe modifié</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        /* RESET */
        body, table, td, p, a {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        body {
            background-color: #f3f4f6;
            padding: 20px;
        }

        .container {
            max-width: 620px;
            margin: auto;
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        /* HEADER */
        .header {
            padding: 20px;
            background: #1e40af;
            color: white;
            text-align: left;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 13px;
            opacity: 0.85;
        }

        /* BODY */
        .body {
            padding: 24px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        /* BLOCK SIMPLE */
        .block {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .label {
            color: #6b7280;
            font-weight: 500;
        }

        .value {
            font-weight: bold;
            color: #111827;
        }

        /* CTA */
        .btn-primary {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 18px;
            background: #2563eb;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        .text-link {
            font-size: 12px;
            text-decoration: underline;
            color: #1e40af;
        }

        /* FOOTER (GARDÉ EXACTEMENT) */
        .footer {
            padding: 20px;
            background: #f3f4f6;
            text-align: left;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .footer-text strong {
            color: #111827;
        }

        .footer-meta {
            font-size: 11px;
            color: #4b5563;
            margin-top: 10px;
        }

        .footer-meta span {
            margin-right: 8px;
            padding-right: 10px;
            border-right: 1px solid #d1d5db;
        }

        .footer-meta span:last-child {
            border-right: none;
            margin-right: 0;
            padding-right: 0;
        }
    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <h1>Weka Akiba — Alerte de sécurité</h1>
        <p>Changement de mot de passe détecté</p>
    </div>

    <!-- BODY -->
    <div class="body">
        <p style="font-size:14px; margin-bottom:16px;">
            Bonjour <strong>{{ $user->name ?? 'Cher client' }}</strong>,<br>
            Nous souhaitons vous informer qu’un changement de mot de passe a été effectué
            sur votre compte <strong>Weka Akiba</strong>.
        </p>

        <p style="font-size:13px; margin-bottom:20px; background:#fef3c7; border:1px solid #fde68a; padding:10px; border-radius:6px;">
            Si vous êtes à l’origine de cette action, vous pouvez ignorer ce message.<br>
            Sinon, sécurisez immédiatement votre compte.
        </p>

        <!-- DÉTAILS DE L'ÉVÉNEMENT -->
        <p class="section-title">Détails de l’événement</p>
        <div class="block">
            <div class="row">
                <span class="label">Date :</span>
                <span class="value">{{ $eventDate ?? now()->format('d/m/Y H:i') }}</span>
            </div>
            <div class="row">
                <span class="label">Appareil :</span>
                <span class="value">{{ $device ?? 'Indisponible' }}</span>
            </div>
            <div class="row">
                <span class="label">Système :</span>
                <span class="value">{{ $os ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Navigateur :</span>
                <span class="value">{{ $browser ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Adresse IP :</span>
                <span class="value">{{ $ip ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Localisation :</span>
                <span class="value">{{ $location ?? 'Indisponible' }}</span>
            </div>
        </div>

        <!-- RÉSUMÉ DU COMPTE -->
        <p class="section-title">Résumé du compte</p>
        <div class="block">
            <div class="row">
                <span class="label">Titulaire :</span>
                <span class="value">{{ $user->name ?? 'Client Weka Akiba' }}</span>
            </div>
            <div class="row">
                <span class="label">ID utilisateur :</span>
                <span class="value">{{ $user->uuid ?? $user->id }}</span>
            </div>
            <div class="row">
                <span class="label">Adresse email :</span>
                <span class="value">{{ $user->email ?? 'Non renseigné' }}</span>
            </div>
            <div class="row">
                <span class="label">2FA :</span>
                <span class="value">{{ $twofa_status ?? 'Non activé' }}</span>
            </div>
        </div>

        <!-- RECOMMANDATION -->
        <div class="block" style="margin-bottom:30px;">
            <p style="font-size:14px; margin-bottom:10px;"><strong>Vous n’êtes pas à l’origine de cette action ?</strong></p>
            <p style="font-size:13px; margin-bottom:14px;">
                Changez immédiatement votre mot de passe et vérifiez l’activité de votre compte.
            </p>

            <a class="btn-primary" href="{{ $security_url ?? '#' }}">Ouvrir le centre de sécurité</a><br>
            <a class="text-link" href="{{ $help_url ?? '#' }}">Contacter le support</a>
        </div>

    </div>

    <!-- FOOTER (inchangé comme demandé) -->
    <div class="footer">
        <p class="footer-text">
            Cet e-mail vous est envoyé automatiquement par le
            <strong>Centre de sécurité Weka Akiba</strong> suite à une action sensible sur votre compte.
            Si vous recevez fréquemment ce type d’alertes sans être à l’origine des actions,
            contactez immédiatement notre équipe de sécurité.
        </p>

        <p class="footer-meta">
            <span>© {{ date('Y') }} Weka Akiba</span>
            <span>Infrastructure bancaire conforme PCI-DSS</span>
            <span>Ne transférez jamais votre mot de passe</span>
        </p>
    </div>

</div>

</body>
</html>
