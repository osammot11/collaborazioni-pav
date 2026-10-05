<?php

namespace App\Mcp\Tools;

use App\Enums\CollaborationStatus;
use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
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
        $write = in_array($this->action, ['create', 'update'], true);
        $security = [['type' => 'oauth2', 'scopes' => [$write ? 'pipeline:write' : 'pipeline:read']]];
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
                'outcome' => ['type' => 'string', 'enum' => array_column(PipelineOutcome::cases(), 'value')],
                'follow_up' => ['type' => 'string', 'enum' => ['overdue', 'today', 'upcoming', 'none']],
                'page' => $integer, 'per_page' => [...$integer, 'maximum' => 50]];
        }
        if (in_array($this->action, ['get', 'update'], true)) {
            $properties['id'] = $integer;
            $required[] = 'id';
        }
        if (in_array($this->action, ['create', 'update'], true)) {
            $fields = [];
            foreach (PipelineIntegration::FIELDS as $field) {
                $fields[$field] = ['type' => in_array($field, ['name', 'status', 'pipeline_stage', 'pipeline_outcome', 'monthly_revenue', 'one_time_revenue']) ? 'string' : ['string', 'null']];
            }
            foreach (['pipeline_stage' => PipelineStage::class, 'pipeline_outcome' => PipelineOutcome::class, 'status' => CollaborationStatus::class] as $field => $enum) {
                $fields[$field]['enum'] = array_column($enum::cases(), 'value');
            }
            foreach (['monthly_revenue', 'one_time_revenue'] as $field) {
                $fields[$field]['description'] = 'Euro senza separatore delle migliaia, punto decimale, es. 1250.00. Minimo 0, massimo 9999999999.99.';
                $fields[$field]['pattern'] = '^\\d{1,10}(\\.\\d{1,2})?$';
            }
            foreach (['payment_deadline', 'demo_date', 'follow_up_date'] as $field) {
                $fields[$field]['description'] = 'Data YYYY-MM-DD oppure null per rimuoverla.';
            }
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
        $write = in_array($this->action, ['create', 'update'], true);
        $scope = $write ? 'pipeline:write' : 'pipeline:read';
        if (! $request->user()?->is_admin || ! $request->user()->tokenCan($scope)) {
            return (new ResponseFactory(Response::error('Autorizzazione OAuth con permesso '.$scope.' richiesta.')))
                ->withMeta('mcp/www_authenticate', ['Bearer resource_metadata="'.OAuthDiscoveryController::issuer()
                    .'/.well-known/oauth-protected-resource/mcp", error="insufficient_scope", scope="'.$scope.'"']);
        }
        try {
            $data = match ($this->action) {
                'options' => $this->options(),
                'search' => $pipeline->search($request->all()),
                'get' => $pipeline->get($request->validate(['id' => 'required|integer|min:1'])['id']),
                default => $pipeline->write($this->action, $request->all(), $request->user()),
            };

            return Response::structured($data);
        } catch (ValidationException $exception) {
            return Response::error(json_encode(['error' => 'validation_or_conflict', 'fields' => $exception->errors()], JSON_UNESCAPED_UNICODE));
        } catch (ModelNotFoundException) {
            return Response::error('Opportunità non trovata: potrebbe essere stata eliminata.');
        }
    }

    private function options(): array
    {
        $map = fn ($enum) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $enum::cases());

        return ['stages' => $map(PipelineStage::class), 'outcomes' => $map(PipelineOutcome::class),
            'payment_statuses' => $map(CollaborationStatus::class), 'currency' => 'EUR', 'dates' => 'YYYY-MM-DD',
            'rules' => ['contratto implica vinto', 'fase/esito commerciale indipendenti dalla situazione del pagamento',
                'update preserva i campi omessi; null cancella un campo opzionale', 'nessuna eliminazione via MCP']];
    }
}
