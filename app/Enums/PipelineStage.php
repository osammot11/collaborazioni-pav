<?php

namespace App\Enums;

enum PipelineStage: string
{
    case Unclassified = 'da_classificare';
    case Prospect = 'da_contattare';
    case Outbound = 'outbound_inviato';
    case Response = 'risposta_ricevuta';
    case DemoScheduled = 'demo_programmata';
    case DemoDone = 'demo_svolta';
    case Quote = 'preventivo_inviato';
    case Negotiation = 'negoziazione';
    case Contract = 'contratto';
    case Paid = 'pagato';
    case Postponed = 'rimandato';
    case Refused = 'rifiutato';

    public function label(): string
    {
        return match ($this) {
            self::Unclassified => 'Da classificare',
            self::Prospect => 'Da contattare',
            self::Outbound => 'Outbound inviato',
            self::Response => 'Risposta ricevuta',
            self::DemoScheduled => 'Demo programmata',
            self::DemoDone => 'Demo svolta',
            self::Quote => 'Preventivo inviato',
            self::Negotiation => 'Negoziazione',
            self::Contract => 'Contratto',
            self::Paid => 'Pagato',
            self::Postponed => 'Rimandato',
            self::Refused => 'Rifiutato',
        };
    }

    public function closed(): bool
    {
        return in_array($this, [self::Contract, self::Paid, self::Refused], true);
    }

    public static function closedValues(): array
    {
        return ['contratto', 'pagato', 'rifiutato'];
    }

    // Retained only for compatibility with historical database columns.
    public function legacyOutcome(): string
    {
        return match ($this) {
            self::Contract, self::Paid => 'vinto',
            self::Refused => 'perso',
            self::Postponed => 'in_attesa',
            default => 'in_corso',
        };
    }
}
