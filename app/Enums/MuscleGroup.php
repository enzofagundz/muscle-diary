<?php

namespace App\Enums;

enum MuscleGroup: string
{
    case Peito = 'Peito';
    case Costas = 'Costas';
    case Ombros = 'Ombros';
    case Trapezio = 'Trapézio';
    case Biceps = 'Bíceps';
    case Triceps = 'Tríceps';
    case Antebraco = 'Antebraço';
    case Quadriceps = 'Quadríceps';
    case Posteriores = 'Posteriores de coxa';
    case Gluteos = 'Glúteos';
    case Panturrilha = 'Panturrilha';
    case Abdomen = 'Abdômen';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
