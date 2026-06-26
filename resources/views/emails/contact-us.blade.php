<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nouveau message</title>
</head>
<body>

<h2>Nouveau message reçu depuis le site Progress Business</h2>

<table cellpadding="6">
    <tr>
        <td><strong>Nom</strong></td>
        <td>{{ $contact['contact_names'] }}</td>
    </tr>

    <tr>
        <td><strong>Email</strong></td>
        <td>{{ $contact['contact_email'] }}</td>
    </tr>

    <tr>
        <td><strong>Téléphone</strong></td>
        <td>{{ $contact['contact_phone'] }}</td>
    </tr>

    <tr>
        <td><strong>Business Type</strong></td>
        <td>{{ $contact['business_type'] }}</td>
    </tr>
</table>

<hr>

<h3>Message</h3>

<p style="white-space: pre-line">
{{ $contact['contact_message'] }}
</p>

</body>
</html>