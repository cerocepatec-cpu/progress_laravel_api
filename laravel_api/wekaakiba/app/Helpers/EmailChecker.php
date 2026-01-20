<?php

namespace App\Helpers;

class EmailChecker
{
    /**
     * Vérifie si une adresse e-mail est valide (format + DNS + SMTP basique).
     *
     * @param string $email
     * @return bool
     */
    public static function isValid(string $email): bool
    {
        // 1️⃣ Vérifie le format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // 2️⃣ Vérifie le domaine (DNS/MX)
        $domain = substr(strrchr($email, "@"), 1);
        if (!$domain || !checkdnsrr($domain, 'MX')) {
            return false;
        }

        // 3️⃣ (Optionnel) Vérifie la connectivité SMTP basique
        $mxHosts = [];
        getmxrr($domain, $mxHosts);

        if (!empty($mxHosts)) {
            $connection = @fsockopen($mxHosts[0], 25, $errno, $errstr, 2);
            if ($connection) {
                fclose($connection);
                return true; // ✅ E-mail considéré valide
            }
        }

        // ❌ Si aucune condition positive
        return false;
    }
}
