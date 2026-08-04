<?php

namespace App\Enums;

enum CollaborationStatus: string
{
    case Paid = 'pagato';
    case Confirmed = 'confermato';
    case Maybe = 'forse';
    case Postponed = 'rimandato';
    case Refused = 'rifiutato';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Pagato',
            self::Confirmed => 'Confermato',
            self::Maybe => 'Forse',
            self::Postponed => 'Rimandato',
            self::Refused => 'Rifiutato',
        };
    }

    public function cssClass(): string
    {
        return 'status-'.$this->value;
    }

    public function isActionable(): bool
    {
        return ! in_array($this, [self::Paid, self::Refused], true);
    }

    /** @return list<string> */
    public static function actionableValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isActionable()),
        );
    }
}
