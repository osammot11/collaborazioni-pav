<?php

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use App\Models\Collaboration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PipelineIntegration
{
    public const FIELDS = ['name', 'description', 'monthly_revenue', 'one_time_revenue', 'status',
        'payment_deadline', 'notes', 'pipeline_stage', 'pipeline_outcome', 'category', 'contact_name',
        'contact_email', 'service', 'demo_type', 'demo_date', 'next_action', 'follow_up_date', 'outcome_reason'];

    public function snapshot(Collaboration $item): array
    {
        $fields = $item->only(self::FIELDS);
        foreach (['pipeline_stage', 'pipeline_outcome', 'status'] as $field) {
            $fields[$field] = $item->$field->value;
        }
        foreach (['payment_deadline', 'demo_date', 'follow_up_date'] as $field) {
            $fields[$field] = $item->$field?->format('Y-m-d');
        }

        return array_merge(['id' => $item->id, 'revision' => $item->revision], $fields,
            ['contract_type' => $item->contractType(), 'updated_at' => $item->updated_at?->toIso8601String()]);
    }

    public function search(array $input): array
    {
        $data = Validator::make($input, [
            'query' => 'sometimes|string|max:255', 'category' => 'sometimes|string|max:255',
            'stage' => ['sometimes', Rule::enum(PipelineStage::class)],
            'outcome' => ['sometimes', Rule::enum(PipelineOutcome::class)],
            'follow_up' => ['sometimes', Rule::in(['overdue', 'today', 'upcoming', 'none'])],
            'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:50',
        ])->validate();
        $query = Collaboration::query()->orderByDesc('id');
        if (isset($data['query'])) {
            $query->where(function ($q) use ($data): void {
                foreach (['name', 'description', 'notes', 'contact_name', 'contact_email', 'service'] as $field) {
                    $q->orWhereRaw($field." LIKE ? ESCAPE '\\'", ['%'.addcslashes($data['query'], '%_\\').'%']);
                }
            });
        }
        foreach (['category' => 'category', 'stage' => 'pipeline_stage', 'outcome' => 'pipeline_outcome'] as $param => $field) {
            if (isset($data[$param])) {
                $query->where($field, $data[$param]);
            }
        }
        if (isset($data['follow_up'])) {
            if ($data['follow_up'] === 'none') {
                $query->whereNull('follow_up_date');
            } else {
                $query->whereNotIn('pipeline_outcome', ['vinto', 'perso'])->whereDate('follow_up_date',
                    match ($data['follow_up']) {
                        'overdue' => '<', 'today' => '=', default => '>'
                    }, today());
            }
        }
        $page = $query->paginate($data['per_page'] ?? 20, ['*'], 'page', $data['page'] ?? 1);

        return ['items' => $page->getCollection()->map(fn ($item) => $this->snapshot($item))->all(),
            'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()];
    }

    public function get(int $id): array
    {
        $item = Collaboration::findOrFail($id);

        return ['opportunity' => $this->snapshot($item), 'history' => $item->pipelineEvents()->limit(50)->get()->toArray()];
    }

    public function write(string $action, array $input, User $user): array
    {
        Validator::make($input, [
            'request_id' => 'required|uuid',
            'id' => $action === 'create' ? 'prohibited' : 'required|integer|min:1',
            'expected_revision' => $action === 'create' ? 'prohibited' : 'required|integer|min:1',
            'fields' => ['required', 'array:'.implode(',', self::FIELDS), 'min:1'],
        ])->validate();
        $client = $user->currentAccessToken()->client_id;
        // Canonicalize field ordering so an identical retried request has the same fingerprint.
        ksort($input['fields']);
        ksort($input);
        $hash = hash('sha256', json_encode([$action, $input], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($action, $input, $user, $client, $hash): array {
            $previous = DB::table('mcp_operations')->where('user_id', $user->id)->where('client_id', $client)
                ->where('request_id', $input['request_id'])->first();
            if ($previous) {
                if (! hash_equals($previous->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['request_id' => 'Questo request_id è già stato usato con dati diversi.']);
                }

                return ['opportunity' => json_decode($previous->after, true), 'replayed' => true];
            }
            $item = $action === 'create' ? new Collaboration : Collaboration::findOrFail($input['id']);
            $before = $item->exists ? $this->snapshot($item) : null;
            if ($item->exists && $item->revision !== $input['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'CONFLICT: dati cambiati. Rileggi l’opportunità e chiedi conferma prima di riprovare.']);
            }
            $fields = $this->validateFields($input['fields'], $item);
            if ($action === 'create') {
                $item->fill(['monthly_revenue' => '0.00', 'one_time_revenue' => '0.00', 'status' => 'forse', ...$fields]);
                $item->save();
            } else {
                $item->fill($fields);
                $dirty = $item->getDirty();
                $changed = $item->isDirty(['pipeline_stage', 'pipeline_outcome']);
                // Atomic compare-and-swap also protects against changes from the web UI.
                $updated = Collaboration::whereKey($item->id)->where('revision', $input['expected_revision'])->update([
                    ...$dirty, 'revision' => $input['expected_revision'] + 1, 'updated_at' => now(),
                ]);
                if (! $updated) {
                    throw ValidationException::withMessages(['expected_revision' => 'CONFLICT: rileggi i dati prima di aggiornare.']);
                }
                $item->refresh();
                if ($changed) {
                    $item->pipelineEvents()->create([
                        'from_stage' => $before['pipeline_stage'], 'to_stage' => $item->pipeline_stage,
                        'from_outcome' => $before['pipeline_outcome'], 'to_outcome' => $item->pipeline_outcome,
                        'note' => $item->outcome_reason,
                    ]);
                }
            }
            $after = $this->snapshot($item->refresh());
            DB::table('mcp_operations')->insert([
                'user_id' => $user->id, 'client_id' => $client, 'request_id' => $input['request_id'],
                'action' => $action, 'payload_hash' => $hash, 'collaboration_id' => $item->id,
                'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            return ['opportunity' => $after, 'replayed' => false];
        }, 3);
    }

    private function validateFields(array $fields, Collaboration $item): array
    {
        $rules = [
            'name' => [$item->exists ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'monthly_revenue' => ['sometimes', 'required', 'string', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/D', 'numeric', 'min:0', 'max:9999999999.99'],
            'one_time_revenue' => ['sometimes', 'required', 'string', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/D', 'numeric', 'min:0', 'max:9999999999.99'],
            'status' => ['sometimes', 'required', Rule::enum(CollaborationStatus::class)],
            'pipeline_stage' => ['sometimes', 'required', Rule::enum(PipelineStage::class)],
            'pipeline_outcome' => ['sometimes', 'required', Rule::enum(PipelineOutcome::class)],
            'contact_email' => 'nullable|email|max:255',
        ];
        foreach (['description', 'notes', 'outcome_reason'] as $field) {
            $rules[$field] = 'nullable|string|max:10000';
        }
        foreach (['category', 'contact_name', 'service', 'demo_type'] as $field) {
            $rules[$field] = 'nullable|string|max:255';
        }
        $rules['next_action'] = 'nullable|string|max:500';
        foreach (['payment_deadline', 'demo_date', 'follow_up_date'] as $field) {
            $rules[$field] = 'nullable|date_format:Y-m-d';
        }
        $data = Validator::make($fields, $rules)->validate();
        $stage = $data['pipeline_stage'] ?? $item->pipeline_stage->value;
        $outcome = $data['pipeline_outcome'] ?? $item->pipeline_outcome->value;
        if ($stage === 'contratto') {
            $data['pipeline_outcome'] = 'vinto';
        } elseif ($outcome === 'vinto') {
            throw ValidationException::withMessages(['pipeline_outcome' => 'Lo stato vinto richiede la fase contratto.']);
        }

        return $data;
    }
}
