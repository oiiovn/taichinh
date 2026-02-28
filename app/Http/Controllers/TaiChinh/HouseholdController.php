<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GoiHienTaiController;
use App\Models\Household;
use App\Models\User;
use App\Services\UserFinancialContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public const HOUSEHOLD_CREATOR_EMAIL = 'giadinh@gmail.com';

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (strtolower(trim((string) $user->email)) !== self::HOUSEHOLD_CREATOR_EMAIL && $user->households()->doesntExist()) {
            return redirect()->route('tai-chinh')->with('error', 'Bạn chưa tham gia nhóm gia đình nào.');
        }
        $memberOnlyHousehold = $user->households()->where('owner_user_id', '!=', $user->id)->first();
        if ($memberOnlyHousehold && $user->ownedHouseholds()->doesntExist()) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.show', $memberOnlyHousehold->id);
        }
        $households = Household::where('owner_user_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['owner', 'members.user'])
            ->orderBy('updated_at', 'desc')
            ->get();
        $planExpiresAt = $user->plan_expires_at;
        $currentPlan = $user->plan;
        $planExpiringSoon = $currentPlan && $planExpiresAt && GoiHienTaiController::planExpiresWithinDays($planExpiresAt, 3);
        $canCreateHousehold = strtolower(trim((string) $user->email)) === self::HOUSEHOLD_CREATOR_EMAIL;
        return view('pages.tai-chinh.nhom-gia-dinh.index', [
            'households' => $households,
            'planExpiresAt' => $planExpiresAt,
            'currentPlan' => $currentPlan,
            'planExpiringSoon' => $planExpiringSoon,
            'canCreateHousehold' => $canCreateHousehold,
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $household = Household::with(['owner', 'members.user'])->findOrFail($id);
        if (! $household->isMember($user)) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.index')->with('error', 'Bạn không có quyền xem nhóm này.');
        }
        $owner = $household->owner;
        $contextSvc = app(UserFinancialContextService::class);
        $context = $contextSvc->ensureCategoriesAndGetContext($owner);
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        try {
            $transactions = ! empty($linkedAccountNumbers)
                ? $contextSvc->getPaginatedTransactions($owner, $linkedAccountNumbers, $request, 50)
                : \App\Models\TransactionHistory::where('user_id', $owner->id)->orderBy('transaction_date', 'desc')->paginate(50)->withQueryString();
        } catch (\Throwable $e) {
            Log::warning('HouseholdController@show getPaginatedTransactions: ' . $e->getMessage());
            $transactions = \App\Models\TransactionHistory::where('user_id', $owner->id)->orderBy('transaction_date', 'desc')->paginate(50)->withQueryString();
        }
        $userCategories = $owner->userCategories()->withCount('transactionHistories')->orderByDesc('transaction_histories_count')->orderBy('type')->orderBy('name')->get();
        $canEdit = $user->id === $household->owner_user_id;
        $accountBalances = $context['accountBalances'] ?? [];
        $totalBalance = is_array($accountBalances) ? array_sum($accountBalances) : 0;
        $depositorNameMap = self::depositorNameMap();
        $planExpiresAt = $user->plan_expires_at;
        $currentPlan = $user->plan;
        $planExpiringSoon = $currentPlan && $planExpiresAt && GoiHienTaiController::planExpiresWithinDays($planExpiresAt, 3);
        return view('pages.tai-chinh.nhom-gia-dinh.show', [
            'household' => $household,
            'transactionHistory' => $transactions,
            'userCategories' => $userCategories,
            'linkedAccountNumbers' => $linkedAccountNumbers,
            'accountBalances' => $accountBalances,
            'totalBalance' => $totalBalance,
            'depositorNameMap' => $depositorNameMap,
            'householdContext' => true,
            'canEdit' => $canEdit,
            'planExpiresAt' => $planExpiresAt,
            'currentPlan' => $currentPlan,
            'planExpiringSoon' => $planExpiringSoon,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (strtolower(trim((string) $user->email)) !== self::HOUSEHOLD_CREATOR_EMAIL) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.index')->with('error', 'Chỉ tài khoản được cấp quyền mới tạo được nhóm gia đình.');
        }
        $request->validate(['name' => 'required|string|max:255']);
        $household = Household::create([
            'name' => $request->input('name'),
            'owner_user_id' => $user->id,
        ]);
        $household->members()->create(['user_id' => $user->id, 'role' => 'owner']);
        return redirect()->route('tai-chinh.nhom-gia-dinh.show', $household->id)->with('success', 'Đã tạo nhóm.');
    }

    public function storeMember(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $household = Household::findOrFail($id);
        if ($household->owner_user_id !== $user->id) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.index')->with('error', 'Chỉ chủ nhóm mới thêm được thành viên.');
        }
        $request->validate(['email' => 'required|email']);
        $invitee = User::where('email', $request->input('email'))->first();
        if (! $invitee) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.show', $id)->with('error', 'Không tìm thấy tài khoản với email này.');
        }
        if ($invitee->id === $user->id) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.show', $id)->with('error', 'Bạn đã là chủ nhóm.');
        }
        if ($household->members()->where('user_id', $invitee->id)->exists()) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.show', $id)->with('error', 'Thành viên đã có trong nhóm.');
        }
        $alreadyInOther = \App\Models\HouseholdMember::where('user_id', $invitee->id)->where('household_id', '!=', $id)->exists();
        if ($alreadyInOther) {
            return redirect()->route('tai-chinh.nhom-gia-dinh.show', $id)->with('error', 'Người này đã tham gia nhóm gia đình khác. Mỗi thành viên chỉ được tham gia một nhóm.');
        }
        $household->members()->create(['user_id' => $invitee->id, 'role' => 'member']);
        return redirect()->route('tai-chinh.nhom-gia-dinh.show', $id)->with('success', 'Đã thêm thành viên.');
    }

    public function giaoDichTable(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->view('pages.tai-chinh.partials.giao-dich-table', [
                'transactionHistory' => \App\Models\TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString(),
                'userCategories' => collect(),
                'linkedAccountNumbers' => [],
                'canEdit' => false,
            ]);
        }
        $household = Household::findOrFail($id);
        if (! $household->isMember($user)) {
            return response()->view('pages.tai-chinh.partials.giao-dich-table', [
                'transactionHistory' => \App\Models\TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString(),
                'userCategories' => collect(),
                'linkedAccountNumbers' => [],
                'canEdit' => false,
            ]);
        }
        $owner = $household->owner;
        $contextSvc = app(UserFinancialContextService::class);
        $context = $contextSvc->ensureCategoriesAndGetContext($owner);
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        $transactions = ! empty($linkedAccountNumbers)
            ? $contextSvc->getPaginatedTransactions($owner, $linkedAccountNumbers, $request, 50)
            : \App\Models\TransactionHistory::where('user_id', $owner->id)->orderBy('transaction_date', 'desc')->paginate(50)->withQueryString();
        $userCategories = $owner->userCategories()->withCount('transactionHistories')->orderByDesc('transaction_histories_count')->orderBy('type')->orderBy('name')->get();
        $canEdit = $user->id === $household->owner_user_id;
        return response()->view('pages.tai-chinh.partials.giao-dich-table', [
            'transactionHistory' => $transactions,
            'userCategories' => $userCategories,
            'linkedAccountNumbers' => $linkedAccountNumbers,
            'householdContext' => true,
            'depositorNameMap' => self::depositorNameMap(),
            'canEdit' => $canEdit,
        ]);
    }

    /** Keyword trong mô tả -> tên user (VUONG, VAN trước VU để tránh match nhầm). */
    protected static function depositorNameMap(): array
    {
        $users = User::whereIn('email', ['admin@gmail.com', 'vuong@gmail.com', 'van@gmail.com'])->get()->keyBy(fn ($u) => strtolower($u->email));
        return [
            'VUONG' => $users->get('vuong@gmail.com')?->name ?? 'Vuong',
            'VAN' => $users->get('van@gmail.com')?->name ?? 'Van',
            'VU' => $users->get('admin@gmail.com')?->name ?? 'Vu',
        ];
    }
}
