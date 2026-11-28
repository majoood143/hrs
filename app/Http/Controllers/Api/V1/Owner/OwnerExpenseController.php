<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Api\V1\Owner\Concerns\FindsOwnerStable;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Http\Resources\V1\Owner\OwnerExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Stable;
use App\Models\StableHorse;
use App\Services\Reports\ExpenseReport;
use App\Services\Reports\StableStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * A stable's own expenses (the /stable panel's Expenses), set against its share of the bookings.
 * Behind the `stable_expenses` feature, which is off while General Settings → Modules → Expenses is.
 */
class OwnerExpenseController extends Controller
{
    use FindsOwnerStable;

    /**
     * My stable's expenses
     *
     * A period's expenses (newest first, at most 300), their totals by category, the stable's share
     * of the bookings in the same period and what is left (profit), plus the categories and horses
     * the form offers.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $data = $request->validate([
            /** Defaults to this_month. */
            'period' => ['nullable', Rule::in(OwnerStableController::PERIODS)],
        ]);
        $period = $data['period'] ?? 'this_month';
        $report = ExpenseReport::forPeriod($stable, $period);
        $income = StableStatement::forPeriod($stable, $period)->totals()['stable_share'];
        $spent = $report->total();
        $money = fn (int $baisa) => new MoneyResource($baisa / 1000);

        return response()->json([
            'period' => [
                'value' => $period,
                'label' => __('admin_income_report.periods.'.$period),
                /** @example 2026-11-01 */
                'from' => $report->from->toDateString(),
                /** @example 2026-11-30 */
                'to' => $report->to->toDateString(),
            ],
            'periods' => collect(OwnerStableController::PERIODS)->map(fn (string $p) => OptionResource::of($p, __('admin_income_report.periods.'.$p)))->all(),
            'totals' => [
                'count' => $report->rows()->count(),
                'expenses' => $money($spent),
                /** The stable's share of the period's bookings (as on the income screen). */
                'income' => $money($income),
                /** income − expenses; may be negative. */
                'profit' => $money($income - $spent),
            ],
            'by_category' => $report->byCategory()->map(fn (array $c) => [
                'category' => OptionResource::of((string) $c['id'], $c['label']),
                'total' => $money($c['total']),
                'count' => $c['count'],
            ])->all(),
            'categories' => collect(ExpenseCategory::options(forStables: true))->map(fn (string $label, int $id) => OptionResource::of((string) $id, $label))->values()->all(),
            'horses' => $stable->horses()->where('is_active', true)->orderBy('name')->get()->map(fn (StableHorse $h) => OptionResource::of((string) $h->id, $h->name))->all(),
            'data' => OwnerExpenseResource::collection($report->rows()->take(300)),
        ]);
    }

    /**
     * Add an expense
     *
     * multipart/form-data when a `receipt` (JPG, PNG, WebP or PDF, up to 5 MB) is sent.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $data = $this->validated($request, $stable, creating: true);

        $expense = $stable->expenses()->create($this->columns($data, $request) + ['user_id' => $request->user('owner_api')->getKey()]);

        return OwnerExpenseResource::make($expense->load(['category', 'horse']))->response()->setStatusCode(201);
    }

    /**
     * Change an expense
     *
     * Sent as POST (multipart forms cannot be PATCHed from every phone). Fields left out stay; a new
     * `receipt` replaces the old one, `remove_receipt=1` removes it.
     */
    public function update(Request $request, string $slug, int $id): OwnerExpenseResource
    {
        $stable = $this->ownerStable($request, $slug);
        $expense = $stable->expenses()->findOrFail($id);
        $data = $this->validated($request, $stable, creating: false);
        $old = $expense->receipt;
        $columns = $this->columns($data, $request);

        if ($request->boolean('remove_receipt') && ! $request->hasFile('receipt')) {
            $columns['receipt'] = null;
        }

        $expense->update($columns);

        if ($old && $old !== $expense->receipt) {
            Storage::disk(Expense::DISK)->delete($old);
        }

        return OwnerExpenseResource::make($expense->load(['category', 'horse']));
    }

    /** Delete an expense */
    public function destroy(Request $request, string $slug, int $id): Response
    {
        $this->ownerStable($request, $slug)->expenses()->findOrFail($id)->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Stable $stable, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $today = now(config('stable_bookings.timezone'))->toDateString();

        return $request->validate([
            'category_id' => [$required, 'integer', Rule::in(array_keys(ExpenseCategory::options(forStables: true)))],
            /** @example 2026-11-20 */
            'spent_on' => [$required, 'date_format:Y-m-d', 'before_or_equal:'.$today],
            /** @example 45.500 */
            'amount' => [$required, 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999999.999'],
            'horse_id' => ['nullable', 'integer', Rule::exists('stable_horses', 'id')->where('stable_id', $stable->getKey())],
            'vendor' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.Expense::RECEIPT_MAX_KB],
            'remove_receipt' => ['nullable', 'boolean'],
        ], [
            'receipt.*' => __('expenses.receipt_invalid'),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function columns(array $data, Request $request): array
    {
        $map = ['category_id' => 'expense_category_id', 'spent_on' => 'spent_on', 'amount' => 'amount', 'horse_id' => 'stable_horse_id', 'vendor' => 'vendor', 'note' => 'note'];
        $columns = [];

        foreach ($map as $field => $column) {
            if (array_key_exists($field, $data)) {
                $columns[$column] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if ($request->hasFile('receipt')) {
            $columns['receipt'] = $request->file('receipt')->store('expenses/receipts', Expense::DISK);
        }

        return $columns;
    }
}
