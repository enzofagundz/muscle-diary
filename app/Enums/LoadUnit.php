<?php

namespace App\Enums;

enum LoadUnit: string
{
    case Kilograms = 'kg';
    case Plates = 'plate';
    case Bodyweight = 'bodyweight';
    case Pounds = 'lb';

    public function label(): string
    {
        return match ($this) {
            self::Kilograms => 'kg',
            self::Plates => 'placas',
            self::Bodyweight => 'peso corporal',
            self::Pounds => 'libras',
        };
    }
}
