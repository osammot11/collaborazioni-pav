<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case Monthly = 'mensile';
    case Quarterly = 'trimestrale';
    case Semiannual = 'semestrale';
    case Annual = 'annuale';
    case Biennial = 'biennale';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensile', self::Quarterly => 'Trimestrale',
            self::Semiannual => 'Semestrale', self::Annual => 'Annuale', self::Biennial => 'Biennale',
        };
    }

    public function suffix(): string
    {
        return match ($this) {
            self::Monthly => '/ mese', self::Quarterly => '/ trimestre',
            self::Semiannual => '/ semestre', self::Annual => '/ anno', self::Biennial => '/ 2 anni',
        };
    }
}
