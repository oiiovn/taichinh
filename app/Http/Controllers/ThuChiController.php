<?php

namespace App\Http\Controllers;

use App\Models\EstimatedExpense;
use App\Models\EstimatedIncome;
use App\Models\EstimatedRecurringTemplate;
use App\Models\IncomeSource;
use App\Services\EstimatedAggregateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ThuChiController extends Controller
{
    public function __construct(
        protected EstimatedAggregateService $aggregate
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $sources = IncomeSource::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recurringTemplates = EstimatedRecurringTemplate::query()
            ->where('user_id', $user->id)
            ->with('source:id,name')
            ->orderByDesc('created_at')
            ->get();

        $validTabs = ['dashboard', 'ghi-chep', 'lich-su'];
        $tab = in_array(request('tab'), $validTabs) ? request('tab') : 'dashboard';

        $historyEntries = collect();
        if ($tab === 'lich-su') {
            $incomes = EstimatedIncome::query()
                ->where('user_id', $user->id)
                ->with('source:id,name')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
            $expenses = EstimatedExpense::query()
                ->where('user_id', $user->id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
            $historyEntries = $incomes->map(fn ($i) => [
                'type' => 'income',
                'id' => $i->id,
                'date' => $i->date,
                'amount' => (float) $i->amount,
                'note' => $i->note,
                'label' => $i->source?->name ?? '—',
                'created_at' => $i->created_at,
            ])->concat($expenses->map(fn ($e) => [
                'type' => 'expense',
                'id' => $e->id,
                'date' => $e->date,
                'amount' => (float) $e->amount,
                'note' => $e->note,
                'label' => $e->category ?? '—',
                'created_at' => $e->created_at,
            ]))->sortByDesc('created_at')->take(100)->values();
        }

        return view('pages.thu-chi.index', [
            'title' => 'Thu chi ước tính',
            'tab' => $tab,
            'today' => $this->aggregate->todaySummary($user->id),
            'monthProjection' => $this->aggregate->monthProjection($user->id),
            'last7Days' => $this->aggregate->last7Days($user->id),
            'bySource' => $this->aggregate->bySource($user->id),
            'sources' => $sources,
            'recurringTemplates' => $recurringTemplates,
            'historyEntries' => $historyEntries,
        ]);
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'source_id' => 'required|exists:income_sources,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        if (IncomeSource::where('id', $data['source_id'])->where('user_id', $user->id)->doesntExist()) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('error', 'Nguồn thu không hợp lệ.');
        }

        EstimatedIncome::create([
            'user_id' => $user->id,
            'source_id' => $data['source_id'],
            'date' => $data['date'],
            'amount' => (int) round((float) $data['amount']),
            'note' => $data['note'] ?? null,
            'mode' => EstimatedIncome::MODE_MANUAL,
        ]);

        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', 'Đã thêm thu nhập ước tính.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        EstimatedExpense::create([
            'user_id' => $user->id,
            'date' => $data['date'],
            'amount' => (int) round((float) $data['amount']),
            'category' => $data['category'] ?? null,
            'note' => $data['note'] ?? null,
            'mode' => EstimatedExpense::MODE_MANUAL,
        ]);

        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', 'Đã thêm chi ước tính.');
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->withErrors($validator)->withInput();
        }

        IncomeSource::create([
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'color' => $request->input('color'),
            'is_active' => true,
        ]);

        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', 'Đã thêm nguồn thu.');
    }

    public function storeRecurringTemplate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:income,expense',
            'source_id' => 'required_if:type,income|nullable|exists:income_sources,id',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        if ($data['type'] === EstimatedRecurringTemplate::TYPE_INCOME) {
            if (IncomeSource::where('id', $data['source_id'])->where('user_id', $user->id)->doesntExist()) {
                return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('error', 'Nguồn thu không hợp lệ.');
            }
        }

        EstimatedRecurringTemplate::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'source_id' => $data['type'] === 'income' ? $data['source_id'] : null,
            'amount' => (int) round((float) $data['amount']),
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'note' => $data['note'] ?? null,
            'category' => $data['category'] ?? null,
        ]);

        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', 'Đã thêm thu/chi định kỳ. Mỗi ngày hệ thống sẽ tự tạo bản ghi theo tần suất.');
    }

    public function destroyRecurringTemplate(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $t = EstimatedRecurringTemplate::where('id', $id)->where('user_id', $user->id)->first();
        if (! $t) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('error', 'Không tìm thấy mẫu định kỳ.');
        }
        $t->delete();
        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', 'Đã xóa mẫu định kỳ.');
    }

    public function toggleRecurringTemplate(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $t = EstimatedRecurringTemplate::where('id', $id)->where('user_id', $user->id)->first();
        if (! $t) {
            return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('error', 'Không tìm thấy mẫu định kỳ.');
        }
        $t->update(['is_active' => ! $t->is_active]);
        return redirect()->route('thu-chi', ['tab' => 'ghi-chep'])->with('success', $t->is_active ? 'Đã bật tự tạo định kỳ.' : 'Đã tắt tự tạo định kỳ.');
    }

    public function editIncome(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $income = EstimatedIncome::where('user_id', $user->id)->findOrFail($id);
        $sources = IncomeSource::query()->where('user_id', $user->id)->where('is_active', true)->orderBy('name')->get();
        return view('pages.thu-chi.edit-income', [
            'title' => 'Sửa thu ước tính',
            'income' => $income,
            'sources' => $sources,
        ]);
    }

    public function updateIncome(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $income = EstimatedIncome::where('user_id', $user->id)->findOrFail($id);
        $validator = Validator::make($request->all(), [
            'source_id' => 'required|exists:income_sources,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return redirect()->route('thu-chi.income.edit', $id)->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        if (IncomeSource::where('id', $data['source_id'])->where('user_id', $user->id)->doesntExist()) {
            return redirect()->route('thu-chi.income.edit', $id)->with('error', 'Nguồn thu không hợp lệ.');
        }
        $income->update([
            'source_id' => $data['source_id'],
            'date' => $data['date'],
            'amount' => (int) round((float) $data['amount']),
            'note' => $data['note'] ?? null,
        ]);
        return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('success', 'Đã cập nhật thu ước tính.');
    }

    public function destroyIncome(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $income = EstimatedIncome::where('user_id', $user->id)->find($id);
        if (! $income) {
            return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('error', 'Không tìm thấy bản ghi.');
        }
        $income->delete();
        return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('success', 'Đã xóa thu ước tính.');
    }

    public function editExpense(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $expense = EstimatedExpense::where('user_id', $user->id)->findOrFail($id);
        return view('pages.thu-chi.edit-expense', [
            'title' => 'Sửa chi ước tính',
            'expense' => $expense,
        ]);
    }

    public function updateExpense(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $expense = EstimatedExpense::where('user_id', $user->id)->findOrFail($id);
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return redirect()->route('thu-chi.expense.edit', $id)->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        $expense->update([
            'date' => $data['date'],
            'amount' => (int) round((float) $data['amount']),
            'category' => $data['category'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
        return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('success', 'Đã cập nhật chi ước tính.');
    }

    public function destroyExpense(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $expense = EstimatedExpense::where('user_id', $user->id)->find($id);
        if (! $expense) {
            return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('error', 'Không tìm thấy bản ghi.');
        }
        $expense->delete();
        return redirect()->route('thu-chi', ['tab' => 'lich-su'])->with('success', 'Đã xóa chi ước tính.');
    }
}
