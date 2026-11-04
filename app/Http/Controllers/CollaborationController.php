<?php

namespace App\Http\Controllers;

use App\Enums\CollaborationStatus;
use App\Enums\RecurringFrequency;
use App\Http\Requests\CollaborationRequest;
use App\Models\Collaboration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollaborationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'deadline' => ['nullable', 'in:overdue,today,upcoming,none'],
        ]);

        $query = Collaboration::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status = ($filters['status'] ?? null)) {
            if (CollaborationStatus::tryFrom($status)) {
                $query->where('status', $status);
            }
        }

        $this->applyDeadlineFilter($query, $filters['deadline'] ?? null);

        $collaborations = $query
            ->orderByRaw('next_payment_due_on IS NULL')
            ->orderBy('next_payment_due_on')
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        $rawTotals = Collaboration::query()
            ->selectRaw('status, SUM(monthly_revenue) as monthly_total, SUM(one_time_revenue) as one_time_total, COUNT(*) as collaborations_count')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Collaboration $row): string => $row->getRawOriginal('status'));

        $totalsByStatus = collect(CollaborationStatus::cases())->mapWithKeys(
            function (CollaborationStatus $status) use ($rawTotals): array {
                $row = $rawTotals->get($status->value);

                return [$status->value => [
                    'status' => $status,
                    'monthly' => (float) ($row?->monthly_total ?? 0),
                    'one_time' => (float) ($row?->one_time_total ?? 0),
                    'count' => (int) ($row?->collaborations_count ?? 0),
                ]];
            },
        );

        return view('collaborations.index', [
            'collaborations' => $collaborations,
            'statuses' => CollaborationStatus::cases(),
            'totalsByStatus' => $totalsByStatus,
            'grandMonthly' => $totalsByStatus->sum('monthly'),
            'grandOneTime' => $totalsByStatus->sum('one_time'),
            'filters' => $filters,
            'recurringTotals' => collect(RecurringFrequency::cases())->mapWithKeys(fn ($frequency) => [$frequency->value => [
                'frequency' => $frequency,
                'amount' => Collaboration::where('recurring_frequency', $frequency->value)->sum('recurring_amount'),
            ]]),
            'recurringByStatus' => DB::table('collaborations')->selectRaw('status, recurring_frequency, SUM(recurring_amount) as amount')
                ->groupBy('status', 'recurring_frequency')->get()->groupBy('status'),
        ]);
    }

    public function create(): View
    {
        return view('collaborations.create', [
            'collaboration' => new Collaboration(['status' => CollaborationStatus::Maybe]),
            'statuses' => CollaborationStatus::cases(),
        ]);
    }

    public function store(CollaborationRequest $request): RedirectResponse|JsonResponse
    {
        $collaboration = DB::transaction(fn () => Collaboration::create($request->validated()));
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Collaborazione aggiunta con successo.',
                'url' => route('collaborations.edit', $collaboration), 'html' => $this->edit($collaboration)->render()], 201);
        }

        return redirect()->route('dashboard')->with('success', 'Collaborazione aggiunta con successo.');
    }

    public function edit(Collaboration $collaboration): View
    {
        return view('collaborations.edit', [
            'collaboration' => $collaboration,
            'statuses' => CollaborationStatus::cases(),
        ]);
    }

    public function update(CollaborationRequest $request, Collaboration $collaboration): RedirectResponse|JsonResponse
    {
        $collaboration = DB::transaction(function () use ($request, $collaboration) {
            $current = Collaboration::findOrFail($collaboration->id);
            if ($request->filled('expected_revision') && $current->revision !== $request->integer('expected_revision')) {
                throw ValidationException::withMessages(['expected_revision' => 'La scheda è cambiata. Riaprila prima di salvare per non sovrascrivere altri pagamenti.']);
            }
            $data = $request->validated();
            unset($data['expected_revision']);
            $current->update($data);

            return $current;
        }, 3);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Collaborazione aggiornata.',
                'url' => route('collaborations.edit', $collaboration), 'html' => $this->edit($collaboration)->render()]);
        }

        return redirect()->route('dashboard')->with('success', 'Collaborazione aggiornata.');
    }

    public function destroy(Collaboration $collaboration): RedirectResponse
    {
        $collaboration->delete();

        return redirect()->route('dashboard')->with('success', 'Collaborazione eliminata.');
    }

    private function applyDeadlineFilter(Builder $query, ?string $deadline): void
    {
        if (! $deadline) {
            return;
        }

        if ($deadline === 'none') {
            $query->whereNull('next_payment_due_on');

            return;
        }

        $query->whereIn('status', CollaborationStatus::actionableValues());

        match ($deadline) {
            'overdue' => $query->whereDate('next_payment_due_on', '<', today()),
            'today' => $query->whereDate('next_payment_due_on', today()),
            'upcoming' => $query->whereDate('next_payment_due_on', '>', today()),
            default => null,
        };
    }
}
