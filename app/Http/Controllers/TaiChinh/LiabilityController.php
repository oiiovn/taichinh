<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\LiabilityAccrual;
use App\Models\LiabilityPayment;
use App\Models\UserLiability;
use App\Notifications\FinanceActivityNotification;
use App\Services\TaiChinh\TaiChinhViewCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class LiabilityController extends Controller
{
    private function redirectToTab(array $query = []): RedirectResponse
    {
        return redirect()->route('tai-chinh', array_merge(['tab' => 'no-khoan-vay'], $query));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        return view('pages.tai-chinh.liability.create', ['title' => 'Thêm khoản nợ / khoản vay']);
    }

    public function thanhToan(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }
        return view('pages.tai-chinh.liability.thanh-toan', [
            'title' => 'Ghi nhận thanh toán',
            'liability' => $liability,
        ]);
    }

    public function ghiLai(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }
        return view('pages.tai-chinh.liability.ghi-lai', [
            'title' => 'Ghi lãi thủ công',
            'liability' => $liability,
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->with(['payments', 'accruals'])->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }
        $outstanding = $liability->outstandingPrincipal();
        $unpaidInterest = $liability->unpaidAccruedInterest();
        $principalStart = (float) $liability->principal;
        $repaid = $principalStart > 0 ? max(0, $principalStart - $outstanding) : 0;
        $progressPercent = $principalStart > 0 ? min(100, round($repaid / $principalStart * 100, 1)) : 0;
        $entries = collect()
            ->merge($liability->payments->map(fn ($p) => (object) ['date' => $p->paid_at, 'type' => 'payment', 'principal_delta' => -(float) $p->principal_portion, 'interest_delta' => -(float) $p->interest_portion, 'raw' => $p]))
            ->merge($liability->accruals->map(fn ($a) => (object) ['date' => $a->accrued_at, 'type' => 'accrual', 'principal_delta' => 0, 'interest_delta' => (float) $a->amount, 'raw' => $a]))
            ->sortByDesc(fn ($e) => $e->date?->format('Y-m-d') ?? '');
        return view('pages.tai-chinh.liability.show', [
            'title' => 'Chi tiết khoản ' . $liability->name,
            'liability' => $liability,
            'outstanding' => $outstanding,
            'unpaidInterest' => $unpaidInterest,
            'progressPercent' => $progressPercent,
            'repaid' => $repaid,
            'principalStart' => $principalStart,
            'entries' => $entries->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $validator = Validator::make($request->all(), [
            'direction' => 'required|in:payable,receivable',
            'name' => 'required|string|max:255',
            'principal' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_unit' => 'required|in:yearly,monthly,daily',
            'interest_calculation' => 'required|in:simple,compound',
            'accrual_frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'auto_accrue' => 'nullable|boolean',
        ], [
            'direction.required' => 'Vui lòng chọn loại.',
            'name.required' => 'Vui lòng nhập tên khoản nợ/vay.',
            'principal.required' => 'Vui lòng nhập số tiền gốc.',
            'principal.min' => 'Số tiền gốc phải lớn hơn 0.',
            'interest_rate.required' => 'Vui lòng nhập lãi suất.',
        ]);

        if ($validator->fails()) {
            return $this->redirectToTab()->withErrors($validator)->withInput()->with('open_modal', 'liability');
        }

        $data = $validator->validated();
        $data['user_id'] = $user->id;
        $data['auto_accrue'] = $request->boolean('auto_accrue', true);
        $data['status'] = UserLiability::STATUS_ACTIVE;

        try {
            UserLiability::create($data);
            TaiChinhViewCache::forget($user->id);
            $msg = $data['direction'] === UserLiability::DIRECTION_RECEIVABLE ? 'Đã thêm khoản cho vay.' : 'Đã thêm khoản nợ / vay.';
            return $this->redirectToTab()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('LiabilityController@store: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToTab()->with('error', 'Không lưu được khoản nợ. Vui lòng thử lại sau.')->withInput()->with('open_modal', 'liability');
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'principal' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'interest_unit' => 'sometimes|in:yearly,monthly,daily',
            'interest_calculation' => 'sometimes|in:simple,compound',
            'accrual_frequency' => 'sometimes|in:daily,weekly,monthly',
            'start_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'auto_accrue' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->redirectToTab()->withErrors($validator)->withInput()->with('open_modal', 'liability-edit-' . $id);
        }

        try {
            $liability->fill($validator->validated());
            $liability->auto_accrue = $request->boolean('auto_accrue', $liability->auto_accrue);
            $liability->save();
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToTab()->with('success', 'Đã cập nhật khoản nợ.');
        } catch (\Throwable $e) {
            Log::error('LiabilityController@update: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToTab()->with('error', 'Không cập nhật được. Vui lòng thử lại sau.')->withInput()->with('open_modal', 'liability-edit-' . $id);
        }
    }

    public function close(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }

        try {
            $liability->update(['status' => UserLiability::STATUS_CLOSED]);
            return $this->redirectToTab()->with('success', 'Đã đóng khoản nợ.');
        } catch (\Throwable $e) {
            Log::error('LiabilityController@close: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToTab()->with('error', 'Không đóng được khoản nợ. Vui lòng thử lại sau.');
        }
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }

        try {
            $liability->delete();
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToTab()->with('success', 'Đã xóa khoản nợ / khoản vay.');
        } catch (\Throwable $e) {
            Log::error('LiabilityController@destroy: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToTab()->with('error', 'Không xóa được. Vui lòng thử lại sau.');
        }
    }

    public function storePayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'principal_portion' => 'required|numeric|min:0',
            'interest_portion' => 'required|numeric|min:0',
        ], [
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'paid_at.required' => 'Vui lòng chọn ngày thanh toán.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tai-chinh.liability.thanh-toan', $id)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['liability_id'] = $liability->id;

        try {
            LiabilityPayment::create($data);
            $user->notify(new FinanceActivityNotification(
                $user->id,
                'đã tạo thanh toán',
                'Khoản nợ',
                $liability->name,
                route('tai-chinh', ['tab' => 'no-khoan-vay']),
                null,
                (float) $data['amount']
            ));
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToTab()->with('success', 'Đã ghi nhận thanh toán.')->with('notification_flash', true);
        } catch (\Throwable $e) {
            Log::error('LiabilityController@storePayment: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.liability.thanh-toan', $id)->with('error', 'Không ghi nhận được thanh toán. Vui lòng thử lại sau.')->withInput();
        }
    }

    public function storeAccrual(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $liability = UserLiability::where('id', $id)->where('user_id', $user->id)->first();
        if (! $liability) {
            return $this->redirectToTab()->with('error', 'Không tìm thấy khoản nợ.');
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'accrued_at' => 'required|date',
        ], [
            'amount.required' => 'Vui lòng nhập số tiền lãi (dương hoặc âm nếu điều chỉnh).',
            'accrued_at.required' => 'Vui lòng chọn ngày phát sinh.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tai-chinh.liability.ghi-lai', $id)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['liability_id'] = $liability->id;
        $data['source'] = LiabilityAccrual::SOURCE_MANUAL;

        try {
            LiabilityAccrual::create($data);
            $amt = (float) $data['amount'];
            if ($amt > 0 && $liability->interest_calculation === UserLiability::INTEREST_CALCULATION_COMPOUND) {
                $liability->principal = (float) $liability->principal + $amt;
                $liability->save();
            }
            $user->notify(new FinanceActivityNotification(
                $user->id,
                'đã ghi lãi',
                'Khoản nợ',
                $liability->name,
                route('tai-chinh', ['tab' => 'no-khoan-vay']),
                null,
                (float) $data['amount']
            ));
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToTab()->with('success', 'Đã ghi nhận lãi thủ công.')->with('notification_flash', true);
        } catch (\Throwable $e) {
            Log::error('LiabilityController@storeAccrual: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.liability.ghi-lai', $id)->with('error', 'Không ghi nhận được lãi. Vui lòng thử lại sau.')->withInput();
        }
    }
}
