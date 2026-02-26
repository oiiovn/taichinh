<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\LoanContract;
use App\Models\LoanContractPersonal;
use App\Models\LoanLedgerEntry;
use App\Models\LoanPendingPayment;
use App\Models\User;
use App\Notifications\FinanceActivityNotification;
use App\Services\LoanLedgerService;
use App\Services\LoanPendingPaymentService;
use App\Services\TaiChinh\TaiChinhViewCache;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class LoanContractController extends Controller
{

    private function redirectToLoans(array $query = []): RedirectResponse
    {
        return redirect()->route('tai-chinh.loans.index', $query);
    }

    private function ledgerService(): LoanLedgerService
    {
        return app(LoanLedgerService::class);
    }

    private function pendingPaymentService(): LoanPendingPaymentService
    {
        return app(LoanPendingPaymentService::class);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        try {
            $asLender = $user->loanContractsAsLender()->with(['borrower', 'ledgerEntries'])->orderBy('created_at', 'desc')->get();
            $asBorrower = $user->loanContractsAsBorrower()->with(['lender', 'ledgerEntries'])->orderBy('created_at', 'desc')->get();

            $contracts = $asLender->merge($asBorrower)->unique('id')->sortByDesc('created_at')->values();

            $summary = [
                'as_lender' => $asLender->where('status', LoanContract::STATUS_ACTIVE)->sum(fn ($c) => $this->ledgerService()->getOutstandingPrincipal($c) + $this->ledgerService()->getUnpaidInterest($c)),
                'as_borrower' => $asBorrower->where('status', LoanContract::STATUS_ACTIVE)->sum(fn ($c) => $this->ledgerService()->getOutstandingPrincipal($c) + $this->ledgerService()->getUnpaidInterest($c)),
            ];
            $summary['net_leverage'] = $summary['as_lender'] - $summary['as_borrower'];

            return view('pages.tai-chinh.loans.index', [
                'title' => 'Hợp đồng vay (Linked)',
                'contracts' => $contracts,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            Log::error('LoanContractController@index: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToLoans()->with('error', 'Không tải được danh sách hợp đồng. Vui lòng thử lại sau.');
        }
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        if ($request->boolean('embed')) {
            return view('pages.tai-chinh.loans.create-embed', ['title' => 'Tạo hợp đồng vay Linked']);
        }
        return view('pages.tai-chinh.loans.create', ['title' => 'Tạo hợp đồng vay Linked']);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'principal_at_start' => 'required|numeric|min:0.01',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_unit' => 'required|in:yearly,monthly,daily',
            'interest_calculation' => 'required|in:simple,compound,reducing_balance',
            'accrual_frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'borrower_email' => 'nullable|email',
            'borrower_external_name' => 'nullable|string|max:255',
            'payment_schedule_enabled' => 'nullable',
            'payment_day_of_month' => 'nullable|integer|min:1|max:28',
        ], [
            'name.required' => 'Vui lòng nhập tên hợp đồng.',
            'principal_at_start.required' => 'Vui lòng nhập số tiền gốc.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tai-chinh', ['tab' => 'no-khoan-vay'])->withErrors($validator)->withInput()->with('open_modal', 'loan');
        }

        $data = $validator->validated();
        $borrowerUserId = null;
        $borrowerExternalName = null;

        if (! empty($data['borrower_email'])) {
            $borrower = User::where('email', trim($data['borrower_email']))->first();
            if ($borrower) {
                $borrowerUserId = $borrower->id;
            } else {
                $borrowerExternalName = trim($data['borrower_email']);
            }
        } elseif (! empty($data['borrower_external_name'])) {
            $borrowerExternalName = trim($data['borrower_external_name']);
        }

        $dayOfMonth = isset($data['payment_day_of_month']) && $data['payment_day_of_month'] > 0 ? (int) $data['payment_day_of_month'] : null;

        try {
            $contract = DB::transaction(function () use ($user, $data, $borrowerUserId, $borrowerExternalName, $request, $dayOfMonth) {
                $c = LoanContract::create([
                    'lender_user_id' => $user->id,
                    'borrower_user_id' => $borrowerUserId,
                    'borrower_external_name' => $borrowerExternalName,
                    'name' => $data['name'],
                    'principal_at_start' => $data['principal_at_start'],
                    'interest_rate' => $data['interest_rate'],
                    'interest_unit' => $data['interest_unit'],
                    'interest_calculation' => $data['interest_calculation'],
                    'accrual_frequency' => $data['accrual_frequency'],
                    'start_date' => $data['start_date'],
                    'due_date' => $data['due_date'] ?? null,
                    'auto_accrue' => true,
                    'payment_schedule_enabled' => ! empty($request->payment_schedule_enabled) && $borrowerUserId,
                    'payment_day_of_month' => $dayOfMonth,
                    'status' => $borrowerUserId ? LoanContract::STATUS_PENDING : LoanContract::STATUS_ACTIVE,
                ]);
                LoanContractPersonal::create([
                    'loan_contract_id' => $c->id,
                    'user_id' => $user->id,
                    'notes' => null,
                ]);
                if ($borrowerUserId) {
                    LoanContractPersonal::create([
                        'loan_contract_id' => $c->id,
                        'user_id' => $borrowerUserId,
                        'notes' => null,
                    ]);
                }
                return $c;
            });

            $msg = $borrowerUserId
                ? 'Đã tạo hợp đồng. Bên vay cần chấp nhận để kích hoạt.'
                : 'Đã tạo hợp đồng (đối tượng ngoài hệ thống).';
            return $this->redirectToLoans()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('LoanContractController@store: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh', ['tab' => 'no-khoan-vay'])->with('error', 'Không tạo được hợp đồng. Vui lòng thử lại sau.')->withInput()->with('open_modal', 'loan');
        }
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::with(['lender', 'borrower', 'ledgerEntries.createdByUser'])
            ->where(function ($q) use ($user) {
                $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
            })
            ->findOrFail($id);

        $outstanding = $this->ledgerService()->getOutstandingPrincipal($contract);
        $unpaidInterest = $this->ledgerService()->getUnpaidInterest($contract);
        $totalAccrued = $this->ledgerService()->getTotalAccruedInterest($contract);
        $pendingPayments = $contract->pendingPayments()
            ->whereIn('status', [LoanPendingPayment::STATUS_AWAITING, LoanPendingPayment::STATUS_PENDING_CONFIRM])
            ->orderBy('due_date')
            ->get();
        $pendingLedgerPayments = $contract->ledgerEntries()
            ->where('type', LoanLedgerEntry::TYPE_PAYMENT)
            ->where('status', LoanLedgerEntry::STATUS_PENDING)
            ->with('createdByUser')
            ->orderBy('created_at')
            ->get();

        return view('pages.tai-chinh.loans.show', [
            'title' => $contract->name,
            'contract' => $contract,
            'outstanding' => $outstanding,
            'unpaidInterest' => $unpaidInterest,
            'totalAccrued' => $totalAccrued,
            'pendingPayments' => $pendingPayments,
            'pendingLedgerPayments' => $pendingLedgerPayments,
        ]);
    }

    public function accept(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::where('id', $id)
            ->where('borrower_user_id', $user->id)
            ->where('status', LoanContract::STATUS_PENDING)
            ->firstOrFail();

        $contract->update(['status' => LoanContract::STATUS_ACTIVE]);
        TaiChinhViewCache::forget($user->id);
        return $this->redirectToLoans()->with('success', 'Đã chấp nhận hợp đồng.');
    }

    public function payment(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);

        if ($contract->status !== LoanContract::STATUS_ACTIVE) {
            return $this->redirectToLoans()->with('error', 'Hợp đồng chưa kích hoạt hoặc đã đóng.');
        }

        return view('pages.tai-chinh.loans.payment', [
            'title' => 'Ghi nhận thanh toán / Thu nợ',
            'contract' => $contract,
            'outstanding' => $this->ledgerService()->getOutstandingPrincipal($contract),
            'unpaidInterest' => $this->ledgerService()->getUnpaidInterest($contract),
        ]);
    }

    public function storePayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);

        if ($contract->status !== LoanContract::STATUS_ACTIVE) {
            return $this->redirectToLoans()->with('error', 'Hợp đồng chưa kích hoạt hoặc đã đóng.');
        }

        $validator = Validator::make($request->all(), [
            'pay_type' => 'required|in:principal,interest,periodic',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tai-chinh.loans.payment', $id)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $amount = (float) $data['amount'];
        $unpaidInterest = $this->ledgerService()->getUnpaidInterest($contract);
        $outstanding = $this->ledgerService()->getOutstandingPrincipal($contract);

        $principalPortion = 0.0;
        $interestPortion = 0.0;
        if ($data['pay_type'] === 'principal') {
            $principalPortion = min($amount, $outstanding);
        } elseif ($data['pay_type'] === 'interest') {
            $interestPortion = min($amount, $unpaidInterest);
        } else {
            $interestPortion = min($amount, $unpaidInterest);
            $principalPortion = min($amount - $interestPortion, $outstanding);
        }

        if ($principalPortion <= 0 && $interestPortion <= 0) {
            return redirect()->route('tai-chinh.loans.payment', $id)->withErrors(['amount' => 'Số tiền vượt quá dư nợ gốc hoặc lãi chưa trả.'])->withInput();
        }

        $source = $contract->lender_user_id === $user->id ? LoanLedgerEntry::SOURCE_LENDER : LoanLedgerEntry::SOURCE_BORROWER;
        $isLinked = $contract->borrower_user_id !== null;
        $status = $isLinked ? LoanLedgerEntry::STATUS_PENDING : LoanLedgerEntry::STATUS_CONFIRMED;

        $idempotencyKey = $request->input('idempotency_key') ? trim($request->input('idempotency_key')) : null;

        try {
            $this->ledgerService()->addPayment(
                $contract,
                $principalPortion,
                $interestPortion,
                $user->id,
                $source,
                Carbon::parse($data['paid_at']),
                $status,
                $idempotencyKey
            );

            $label = $contract->borrower_user_id === $user->id ? 'thanh toán' : 'thu nợ';

            $amount = (float) $data['amount'];
            $amountStr = number_format($amount, 0, ',', '.') . ' ₫';
            if ($isLinked) {
                $counterparty = $contract->lender_user_id === $user->id ? $contract->borrower : $contract->lender;
                if ($counterparty) {
                    $counterparty->notify(new FinanceActivityNotification(
                        $counterparty->id,
                        'gửi yêu cầu xác nhận thanh toán ' . $amountStr . ' trong ',
                        'Hợp đồng vay',
                        $contract->name,
                        route('tai-chinh.loans.show', $id),
                        $user,
                        $amount
                    ));
                }
                if ($label === 'thu nợ') {
                    $user->notify(new FinanceActivityNotification(
                        $user->id,
                        'đã tạo thu nợ',
                        'Hợp đồng vay',
                        $contract->name,
                        route('tai-chinh.loans.show', $id),
                        null,
                        $amount
                    ));
                }
            } else {
                $user->notify(new FinanceActivityNotification(
                    $user->id,
                    'đã ' . $label,
                    'Hợp đồng vay',
                    $contract->name,
                    route('tai-chinh.loans.show', $id),
                    null,
                    $amount
                ));
            }

            $msg = $isLinked
                ? "Đã ghi nhận {$label}. Bên kia cần xác nhận thì giao dịch mới hoàn tất."
                : "Đã ghi nhận {$label}.";
            return redirect()->route('tai-chinh.loans.show', $id)->with('success', $msg)->with('notification_flash', true);
        } catch (\Throwable $e) {
            Log::error('LoanContractController@storePayment: ' . $e->getMessage(), ['user_id' => $user->id, 'contract_id' => $id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.loans.payment', $id)->with('error', 'Không ghi nhận được thanh toán. Vui lòng thử lại sau.')->withInput();
        }
    }

    public function confirmPaymentEntry(Request $request, int $id, int $entryId): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }
        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);
        $entry = LoanLedgerEntry::where('loan_contract_id', $contract->id)
            ->where('id', $entryId)
            ->where('type', LoanLedgerEntry::TYPE_PAYMENT)
            ->where('status', LoanLedgerEntry::STATUS_PENDING)
            ->firstOrFail();
        $isCounterparty = ($user->id === (int) $contract->lender_user_id && $entry->source === LoanLedgerEntry::SOURCE_BORROWER)
            || ($user->id === (int) $contract->borrower_user_id && $entry->source === LoanLedgerEntry::SOURCE_LENDER);
        if (! $isCounterparty) {
            return redirect()->route('tai-chinh.loans.show', $id)->with('error', 'Chỉ bên đối phương mới được xác nhận.');
        }

        try {
            $entry->update(['status' => LoanLedgerEntry::STATUS_CONFIRMED]);

            $entryAmount = abs((float) $entry->principal_delta) + abs((float) $entry->interest_delta);
            $amountStr = number_format($entryAmount, 0, ',', '.') . ' ₫';
            $creator = $entry->source === LoanLedgerEntry::SOURCE_BORROWER ? $contract->borrower : $contract->lender;
            if ($creator) {
                $creator->notify(new FinanceActivityNotification(
                    $creator->id,
                    'đã xác nhận thanh toán ' . $amountStr . ' trong ',
                    'Hợp đồng vay',
                    $contract->name,
                    route('tai-chinh.loans.show', $id),
                    $user,
                    $entryAmount
                ));
            }
            TaiChinhViewCache::forget($user->id);
            return redirect()->route('tai-chinh.loans.show', $id)->with('success', 'Đã xác nhận thanh toán.');
        } catch (\Throwable $e) {
            Log::error('LoanContractController@confirmPaymentEntry: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.loans.show', $id)->with('error', 'Không xác nhận được. Vui lòng thử lại sau.');
        }
    }

    public function rejectPaymentEntry(Request $request, int $id, int $entryId): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }
        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);
        $entry = LoanLedgerEntry::where('loan_contract_id', $contract->id)
            ->where('id', $entryId)
            ->where('type', LoanLedgerEntry::TYPE_PAYMENT)
            ->where('status', LoanLedgerEntry::STATUS_PENDING)
            ->firstOrFail();
        $isCounterparty = ($user->id === (int) $contract->lender_user_id && $entry->source === LoanLedgerEntry::SOURCE_BORROWER)
            || ($user->id === (int) $contract->borrower_user_id && $entry->source === LoanLedgerEntry::SOURCE_LENDER);
        if (! $isCounterparty) {
            return redirect()->route('tai-chinh.loans.show', $id)->with('error', 'Chỉ bên đối phương mới được từ chối.');
        }

        try {
            $entry->update(['status' => LoanLedgerEntry::STATUS_REJECTED]);

            $entryAmount = abs((float) $entry->principal_delta) + abs((float) $entry->interest_delta);
            $amountStr = number_format($entryAmount, 0, ',', '.') . ' ₫';
            $creator = $entry->source === LoanLedgerEntry::SOURCE_BORROWER ? $contract->borrower : $contract->lender;
            if ($creator) {
                $creator->notify(new FinanceActivityNotification(
                    $creator->id,
                    'đã từ chối thanh toán ' . $amountStr . ' trong ',
                    'Hợp đồng vay',
                    $contract->name,
                    route('tai-chinh.loans.show', $id),
                    $user,
                    $entryAmount
                ));
            }

            return redirect()->route('tai-chinh.loans.show', $id)->with('success', 'Đã từ chối giao dịch thanh toán.');
        } catch (\Throwable $e) {
            Log::error('LoanContractController@rejectPaymentEntry: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.loans.show', $id)->with('error', 'Không từ chối được. Vui lòng thử lại sau.');
        }
    }

    public function close(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);

        if ((int) $contract->borrower_user_id === (int) $user->id) {
            return $this->redirectToLoans()->with('error', 'Chỉ bên cho vay mới được đóng hợp đồng.');
        }

        try {
            $contract->update(['status' => LoanContract::STATUS_CLOSED]);
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToLoans()->with('success', 'Đã đóng hợp đồng.');
        } catch (\Throwable $e) {
            Log::error('LoanContractController@close: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToLoans()->with('error', 'Không đóng được hợp đồng. Vui lòng thử lại sau.');
        }
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $contract = LoanContract::where(function ($q) use ($user) {
            $q->where('lender_user_id', $user->id)->orWhere('borrower_user_id', $user->id);
        })->findOrFail($id);

        if ((int) $contract->borrower_user_id === (int) $user->id) {
            return $this->redirectToLoans()->with('error', 'Chỉ bên cho vay mới được xóa hợp đồng.');
        }

        try {
            $contract->delete();
            TaiChinhViewCache::forget($user->id);
            return $this->redirectToLoans()->with('success', 'Đã xóa hợp đồng vay.');
        } catch (\Throwable $e) {
            Log::error('LoanContractController@destroy: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $this->redirectToLoans()->with('error', 'Không xóa được hợp đồng. Vui lòng thử lại sau.');
        }
    }

    public function recordPendingPayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $pending = LoanPendingPayment::with('loanContract')->findOrFail($id);
        $contract = $pending->loanContract;
        if (! $contract || ($contract->lender_user_id !== $user->id && $contract->borrower_user_id !== $user->id)) {
            return $this->redirectToLoans()->with('error', 'Không có quyền.');
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:bank,cash',
            'bank_transaction_ref' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tai-chinh.loans.show', $contract->id)->withErrors($validator)->withInput();
        }

        try {
            $this->pendingPaymentService()->recordManualPayment(
                $pending,
                $user->id,
                $validator->validated()['payment_method'],
                $validator->validated()['bank_transaction_ref'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tai-chinh.loans.show', $contract->id)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('LoanContractController@recordPendingPayment: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.loans.show', $contract->id)->with('error', 'Không ghi nhận được thanh toán. Vui lòng thử lại sau.');
        }

        return redirect()->route('tai-chinh.loans.show', $contract->id)->with('success', 'Đã ghi nhận thanh toán. Chờ đối phương xác nhận.');
    }

    public function confirmPendingPayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirectToLoans()->with('error', 'Vui lòng đăng nhập.');
        }

        $pending = LoanPendingPayment::with('loanContract')->findOrFail($id);
        $contract = $pending->loanContract;

        try {
            $this->pendingPaymentService()->confirmByCounterparty($pending, $user->id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tai-chinh.loans.show', $contract->id)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('LoanContractController@confirmPendingPayment: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return redirect()->route('tai-chinh.loans.show', $contract->id)->with('error', 'Không xác nhận được. Vui lòng thử lại sau.');
        }
        TaiChinhViewCache::forget($user->id);
        return redirect()->route('tai-chinh.loans.show', $contract->id)->with('success', 'Đã xác nhận thanh toán.');
    }
}
