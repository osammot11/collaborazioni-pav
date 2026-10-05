<?php

namespace App\Enums;

enum PipelineOutcome: string
{
    case Active = 'in_corso';
    case Waiting = 'in_attesa';
    case Maybe = 'forse';
    case NoResponse = 'senza_risposta';
    case Lost = 'perso';
    case Won = 'vinto';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'In corso / interessato',
            self::Waiting => 'In attesa / rimandato',
            self::Maybe => 'Forse',
            self::NoResponse => 'Senza risposta / senza seguito',
            self::Lost => 'Rifiutato / perso',
            self::Won => 'Acquisito',
        };
    }

    public function closed(): bool
    {
        return in_array($this, [self::Lost, self::Won], true);
    }
}
