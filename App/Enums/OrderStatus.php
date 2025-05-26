<?php
namespace App\Enums;

enum OrderStatus: int
{
    case DRAFT = 0;
    case PENDING = 1;
    case TO_BE_ORDERED = 2;
    case CONFIRMED = 3;
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
