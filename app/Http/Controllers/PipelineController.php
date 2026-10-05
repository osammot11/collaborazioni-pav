<?php

namespace App\Http\Controllers;

use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use App\Http\Requests\PipelineUpdateRequest;
use App\Models\Collaboration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'outcome' => ['nullable', Rule::enum(PipelineOutcome::class)],
            'stage' => ['nullable', Rule::enum(PipelineStage::class)],
            'follow_up' => ['nullable', 'in:due,upcoming,none'],
        ]);

        $query = Collaboration::query();
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($builder) use ($search) {
                foreach (['name', 'contact_name', 'contact_email', 'service', 'notes'] as $field) {
                    $builder->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }
        foreach (['category' => 'category', 'outcome' => 'pipeline_outcome', 'stage' => 'pipeline_stage'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($followUp = ($filters['follow_up'] ?? null)) {
            if ($followUp === 'none') {
                $query->whereNull('follow_up_date');
            } else {
                $query->whereNotIn('pipeline_outcome', ['perso', 'vinto'])
                    ->whereDate('follow_up_date', $followUp === 'due' ? '<=' : '>', today());
            }
        }

        $summary = (clone $query)->selectRaw('pipeline_stage, COUNT(*) as total')->groupBy('pipeline_stage')->pluck('total', 'pipeline_stage');
        // Bound each column independently so a large outbound list stays usable.
        $columns = collect(PipelineStage::cases())->mapWithKeys(function ($stage) use ($query, $summary) {
            return [$stage->value => [
                'stage' => $stage,
                'total' => (int) ($summary[$stage->value] ?? 0),
                'items' => (clone $query)->where('pipeline_stage', $stage->value)
                    ->orderByRaw('follow_up_date IS NULL')->orderBy('follow_up_date')->latest('id')->limit(30)->get(),
            ]];
        });

        return view('pipeline.index', [
            'columns' => $columns,
            'stages' => PipelineStage::cases(),
            'outcomes' => PipelineOutcome::cases(),
            'filters' => $filters,
            'categories' => Collaboration::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'results' => (clone $query)->orderByRaw('follow_up_date IS NULL')->orderBy('follow_up_date')->latest('id')->paginate(24)->withQueryString(),
            'dueCount' => Collaboration::whereNotIn('pipeline_outcome', ['perso', 'vinto'])->whereDate('follow_up_date', '<=', today())->count(),
        ]);
    }

    public function update(PipelineUpdateRequest $request, Collaboration $collaboration): RedirectResponse
    {
        DB::transaction(fn () => $collaboration->update($request->validated()));

        return redirect()->route('pipeline')->with('success', 'Pipeline aggiornata.');
    }
}
