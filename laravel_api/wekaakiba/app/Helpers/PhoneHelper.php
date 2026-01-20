<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;
use libphonenumber\PhoneNumberType;
class PhoneHelper
{
     /**
     * Vérifie si un numéro de téléphone est valide pour un pays donné.
     *
     * @param string $phone Le numéro à vérifier (ex: +243991518333 ou 0991518333)
     * @param string $countryCode Le code ISO du pays (ex: 'CD', 'FR', 'US')
     * @return bool
     */
    public static function isValidPhoneNumber(string $phonenumber, string $countryCode): bool
    {
                try {
            Log::info("Vérification du numéro: {$phonenumber} pour le pays: {$countryCode}");

            // Création de l'objet PhoneNumber
            $phone = PhoneNumber::make($phonenumber, $countryCode);

            // Vérifie que le pays détecté correspond
            $detectedCountry = $phone->getCountry();
            if (!$detectedCountry) {
                Log::warning("Numéro invalide : aucun pays détecté pour {$phonenumber}");
                return false;
            }
            Log::info("Pays détecté : {$detectedCountry}");

            if (!$phone->isOfCountry($countryCode)) {
                Log::warning("Numéro {$phonenumber} n'appartient pas au pays {$countryCode}");
                return false;
            }

            // Vérification du type de numéro
            $type = $phone->getType();
            $typeName = match($type) {
                PhoneNumberType::FIXED_LINE => 'fixed_line',
                PhoneNumberType::MOBILE => 'mobile',
                PhoneNumberType::FIXED_LINE_OR_MOBILE => 'fixed_line_or_mobile',
                PhoneNumberType::TOLL_FREE => 'toll_free',
                default => 'other',
            };
            Log::info("Type de numéro détecté: {$typeName}");

            // if (!in_array($type, [
            //     PhoneNumberType::MOBILE,
            //     PhoneNumberType::FIXED_LINE,
            //     PhoneNumberType::FIXED_LINE_OR_MOBILE
            // ])) {
            //     Log::warning("Numéro {$phonenumber} n'est ni mobile ni fixe");
            //     return false;
            // }

            // Vérification de la longueur nationale
            // $nationalNumber = $phone->getNationalNumber();
            // if (strlen($nationalNumber) < 8) {
            //     Log::warning("Numéro {$phonenumber} trop court ({$nationalNumber})");
            //     return false;
            // }

            Log::info("Numéro {$phonenumber} est valide ✅");
            return true;

        } catch (\Throwable $e) {
            Log::error("Exception lors de la vérification du numéro {$phonenumber}: " . $e->getMessage());
            return false;
        }

    }
    
    /**
     * Vérifie si un numéro de téléphone est valide pour un pays donné.
     *
     * @param string $phone Le numéro à vérifier (ex: +243991518333 ou 0991518333)
     * @param string $countryCode Le code ISO du pays (ex: 'CD', 'FR', 'US')
     * @return bool
     */
    public static function isValidPhone(string $phone, string $countryCode): bool
    {
        try {
            // Nettoyer les espaces et caractères non numériques sauf le +
            $phone = preg_replace('/[^\d+]/', '', $phone);

            // Créer l'instance PhoneNumber
            $instance = PhoneNumber::make($phone, $countryCode);

            // Vérifier que le numéro est bien du pays attendu
            if (! $instance->isOfCountry($countryCode)) {
                return false;
            }

            // Vérifier que la longueur nationale est réaliste (au moins 8 chiffres)
            $nationalNumber = $instance->getNationalNumber();
            if (strlen($nationalNumber) < 8) {
                return false;
            }

            // Optionnel : vérifier le type du numéro (mobile ou fixe)
            // $type = $instance->getType();
            // if (!in_array($type, [\libphonenumber\PhoneNumberType::MOBILE, \libphonenumber\PhoneNumberType::FIXED_LINE])) {
            //     return false;
            // }

            return true;

        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Formate le numéro au format E.164 standard (+243970000000)
     */
    public static function formatPhone(string $phone): ?string
    {
        try {
            $number = PhoneNumber::make($phone)->ofCountry(null);
            return $number->formatE164();
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Retourne le code pays détecté (ex: CD, FR, US)
     */
    public static function getCountry(string $phone): ?string
    {
        try {
            $number = PhoneNumber::make($phone)->ofCountry(null);
            return $number->getCountry();
        } catch (\Throwable $th) {
            return null;
        }
    }

     /**
     * Retourne le numéro sans le code pays.
     * 
     * @param string $phone Numéro complet (+243991516369)
     * @param bool $withZero true → préfixer avec 0, false → sans 0
     * @return string|null
     */
    public static function removeCountryCode(string $phone, bool $withZero = true): ?string
    {
        try {
            // On récupère le numéro au format national (ex: 0991516369)
            $number = PhoneNumber::make($phone)->ofCountry(null)->formatNational();

            // On nettoie les espaces ou séparateurs éventuels
            $clean = preg_replace('/\D/', '', $number);

            // Si on veut sans le zéro initial
            if (!$withZero && str_starts_with($clean, '0')) {
                $clean = substr($clean, 1);
            }

            return $clean;
        } catch (\Throwable $th) {
            return null;
        }
    }
}
