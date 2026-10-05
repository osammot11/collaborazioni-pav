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
        };
    }
}
