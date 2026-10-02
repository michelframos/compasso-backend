<?php

namespace App\Modules\Core\Support;

final class SiteModuloIcones
{
    /** @var list<string> */
    public const WHITELIST = [
        'Users',
        'UserRoundPen',
        'UserPlus',
        'GraduationCap',
        'CalendarDays',
        'Wallet',
        'Star',
        'Music',
        'BarChart3',
        'MessageCircle',
        'LayoutGrid',
    ];

    public static function normalizar(?string $icone): string
    {
        $icone = $icone ?: 'LayoutGrid';

        return in_array($icone, self::WHITELIST, true) ? $icone : 'LayoutGrid';
    }
}
