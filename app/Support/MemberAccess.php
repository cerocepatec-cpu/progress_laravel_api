<?php

namespace App\Support;

use App\Models\User;

class MemberAccess
{
    public const CATEGORY_ADMIN = 6;

    public const CATEGORY_MEMBER = 7;

    public const CATEGORY_BACKOFFICE_USER = 9;

    public static function isAdminIt(?User $member): bool
    {
        return $member !== null && (int) $member->member_code === 1;
    }

    public static function isBackofficeAdmin(?User $member): bool
    {
        return self::isAdminIt($member)
            || ($member !== null && (int) $member->categorie_id === self::CATEGORY_ADMIN);
    }

    public static function isBackofficeUser(?User $member): bool
    {
        return self::isBackofficeAdmin($member)
            || ($member !== null && (int) $member->categorie_id === self::CATEGORY_BACKOFFICE_USER);
    }

    public static function isMember(?User $member): bool
    {
        return $member !== null && (int) $member->categorie_id === self::CATEGORY_MEMBER;
    }

    public static function allows(?User $member, string $scope): bool
    {
        return match ($scope) {
            'member' => self::isMember($member),
            'backoffice' => self::isBackofficeUser($member),
            'backoffice_admin' => self::isBackofficeAdmin($member),
            'backoffice_admin_it' => self::isAdminIt($member),
            default => false,
        };
    }
}

