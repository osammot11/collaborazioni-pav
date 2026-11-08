<?php

namespace App\Mcp\Tools;

use App\Enums\CollaborationStatus;
use App\Enums\PipelineStage;
use App\Enums\RecurringFrequency;
use App\Http\Controllers\OAuthDiscoveryController;
use App\Services\PipelineIntegration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class PipelineTool extends Tool
{
    protected string $action;

    public function toArray(): array
    {
        $result = parent::toArray();
        $write = in_array($this->action, ['create', 'update', 'delete'], true);
        $security = [['type' => 'oauth2', 'scopes' => [$this->scope()]]];
        $result['securitySchemes'] = $security;
        $result['_meta']['securitySchemes'] = $security;
        $result['annotations'] = ['readOnlyHint' => ! $write, 'destructiveHint' => $write,
            'idempotentHint' => true, 'openWorldHint' => false];
        $result['inputSchema'] = $this->inputSchema();

        return $result;
    }

    private function inputSchema(): array
    {
        $string = ['type' => 'string'];
        $integer = ['type' => 'integer', 'minimum' => 1];
        $properties = [];
        $required = [];
        if ($this->action === 'search') {
            $properties = ['query' => $string, 'category' => $string,
                'stage' => ['type' => 'string', 'enum' => array_column(PipelineStage::cases(), 'value')],
                'follow_up' => ['type' => 'string', 'enum' => ['overdue', 'today', 'upcoming', 'none']],
                'renewal' => ['type' => 'string', 'enum' => ['due', 'upcoming', 'none']],
                'payment_due' => ['type' => 'string', 'enum' => ['due', 'upcoming', 'none']],
                'page' => $integer, 'per_page' => [...$integer, 'maximum' => 50]];
        }
        if (in_array($this->action, ['get', 'update', 'delete'], true)) {
            $properties['id'] = $integer;
            $required[] = 'id';
        }

        if ($this->action === 'delete') {
            $properties['expected_revision'] = [...$integer, 'description' => 'Revision corrente letta con get_opportunity, prima della conferma utente.'];
            $properties['confirm_name'] = ['type' => 'string', 'description' => 'Nome esatto del contatto riletto e confermato dall’utente. Non costituisce prova del consenso: chiedilo in chat.'];
            $properties['request_id'] = ['type' => 'string', 'format' => 'uuid', 'description' => 'UUID idempotenza: riusa solo per ritentare la stessa eliminazione.'];
            $required = [...$required, 'expected_revision', 'confirm_name', 'request_id'];
        }
        if (in_array($this->action, ['create', 'update'], true)) {
            $fields = [];
            foreach ([...PipelineIntegration::FIELDS, ...PipelineIntegration::FINANCE_FIELDS] as $field) {
                $fields[$field] = ['type' => in_array($field, ['name', 'status', 'pipeline_stage', 'monthly_revenue', 'one_time_revenue']) ? 'string' : ['string', 'null']];
            }
            foreach (['pipeline_stage' => PipelineStage::class, 'status' => CollaborationStatus::class] as $field => $enum) {
                $fields[$field]['enum'] = array_column($enum::cases(), 'value');
            }
            $fields['recurring_frequency'] = ['type' => 'string', 'enum' => array_column(RecurringFrequency::cases(), 'value')];
            $fields['recurring_amount'] = ['type' => 'string', 'description' => 'Importo reale di ogni rinnovo. Per 1200 EUR annuali usa 1200.00 e frequenza annuale, non 100 mensili.'];
            $fields['payment_total'] = ['type' => ['string', 'null'], 'description' => 'Totale da saldare per accordo/periodo esplicito payment_scope; non dedurlo dai rinnovi futuri. Obbligatorio con rate o incassi.'];
            foreach (['payment_entries' => 'paid_on', 'payment_schedule' => 'due_on'] as $list => $date) {
                $fields[$list] = ['type' => 'array', 'maxItems' => 200,
                    'description' => 'Sostituisce l’intero elenco: rileggi, conserva tutte le righe non modificate e chiedi conferma delle rimozioni. '.($list === 'payment_entries' ? 'Solo incassi reali non futuri.' : 'Rate previste, non incassi.'),
                    'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['amount', $date],
                        'properties' => ['amount' => ['type' => 'string', 'pattern' => '^\\d{1,10}(\\.\\d{1,2})?$', 'description' => 'EUR maggiori di zero'],
                            $date => ['type' => 'string', 'format' => 'date'], 'note' => ['type' => ['string', 'null'], 'maxLength' => 500]]]];
            }
            foreach (['monthly_revenue', 'one_time_revenue'] as $field) {
                $fields[$field]['description'] = 'Euro senza separatore delle migliaia, punto decimale, es. 1250.00. Minimo 0, massimo 9999999999.99.';
                $fields[$field]['pattern'] = '^\\d{1,10}(\\.\\d{1,2})?$';
            }
            foreach (['payment_deadline', 'paid_at', 'demo_date', 'follow_up_date', 'next_renewal_date'] as $field) {
                $fields[$field]['description'] = 'Data YYYY-MM-DD oppure null per rimuoverla.';
            }
            $fields['paid_at']['description'] = 'Data del saldo YYYY-MM-DD, non futura. Con payment_total viene calcolata dagli incassi; senza piano è richiesta per pagato. Non inventarla.';
            $fields['monthly_revenue']['description'] = 'Compatibilità storica: solo importi mensili. Non usare insieme a recurring_amount/recurring_frequency; preferisci questi ultimi.';
            $properties['fields'] = ['type' => 'object', 'properties' => $fields, 'additionalProperties' => false,
                'minProperties' => 1, 'required' => $this->action === 'create' ? ['name'] : []];
            $properties['request_id'] = ['type' => 'string', 'format' => 'uuid', 'description' => 'UUID idempotenza: stesso valore solo per ritentare identica operazione.'];
            $required = [...$required, 'fields', 'request_id'];
            if ($this->action === 'update') {
                $properties['expected_revision'] = [...$integer, 'description' => 'Revision letta da get_opportunity. Un conflitto richiede nuova lettura, non un retry alla cieca.'];
                $required[] = 'expected_revision';
            }
        }

        return ['type' => 'object', 'properties' => $properties ?: (object) [], 'required' => $required, 'additionalProperties' => false];
    }

    public function handle(Request $request, PipelineIntegration $pipeline): Response|ResponseFactory
    {
        $scope = $this->scope();
        if (! $request->user()?->is_admin || ! $request->user()->tokenCan($scope)) {
            return (new ResponseFactory(Response::error('Autorizzazione OAuth con permesso '.$scope.' richiesta.')))
                ->withMeta('mcp/www_authenticate', ['Bearer resource_metadata="'.OAuthDiscoveryController::metadataUrl(request())
                    .'", error="insufficient_scope", scope="'.$scope.'"']);
        }
        try {
            $data = match ($this->action) {
                'options' => $this->options(),
                'search' => $pipeline->search($request->all()),
                'get' => $pipeline->get($request->validate(['id' => 'required|integer|min:1'])['id']),
                'delete' => $pipeline->delete($request->all(), $request->user()),
                default => $pipeline->write($this->action, $request->all(), $request->user()),
            };

            return Response::structured($data);
        } catch (ValidationException $exception) {
            return Response::error(json_encode(['error' => 'validation_or_conflict', 'fields' => $exception->errors()], JSON_UNESCAPED_UNICODE));
        } catch (ModelNotFoundException) {
            return Response::error('Opportunità non trovata: potrebbe essere stata eliminata.');
        }
    }

    private function scope(): string
    {
        return match ($this->action) {
            'delete' => 'pipeline:delete',
            'create', 'update' => 'pipeline:write',
            default => 'pipeline:read',
        };
    }

    private function options(): array
    {
        $map = fn ($enum) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $enum::cases());

        return ['stages' => $map(PipelineStage::class),
            'recurring_frequencies' => $map(RecurringFrequency::class),
            'payment_statuses' => $map(CollaborationStatus::class), 'currency' => 'EUR', 'dates' => 'YYYY-MM-DD',
            'rules' => ['Un solo stato pipeline_stage: nessun esito separato. Contratto non implica pagamento', 'pagato imposta situazione pagato; richiede paid_at non futura',
                'rifiutato e rimandato impostano la corrispondente situazione; conservano dati e storico',
                'riaprire da rifiutato/rimandato ripristina situazione forse salvo una diversa situazione esplicita',
                'payment_total è il totale del periodo/accordo: payment_entries sono incassi effettivi, payment_schedule sono rate previste',
                'Con un piano: incasso parziale implica parzialmente_pagato; solo il saldo implica pagato e calcola paid_at. Mai inventare incassi per completare un saldo',
                'recurring_amount è l’importo reale per recurring_frequency; next_renewal_date non avanza automaticamente e non crea incassi',
                'update preserva i campi omessi; null cancella un campo opzionale',
                'delete è definitivo, richiede pipeline:delete, revisione, nome esatto e conferma esplicita dell’utente']];
    }
}
