<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Luồng: Giao diện chính (sau đăng nhập)
| Middleware: auth
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/notifications/unread-count', function () {
        return response()->json(['unread_count' => auth()->user()->unreadNotifications()->count()]);
    })->name('notifications.unread-count');

    // Tạm thời: trang chủ điều hướng đến Tài chính → Chiến lược
    Route::get('/', function () {
        return redirect()->route('tai-chinh', ['tab' => 'chien-luoc']);
    })->name('dashboard');

    Route::get('/calendar', function () {
        return view('pages.calender', ['title' => 'Calendar']);
    })->name('calendar');

    Route::get('/profile', function () {
        return view('pages.profile', ['title' => 'Hồ sơ']);
    })->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/goi-hien-tai', function () {
        $user = auth()->user();
        return view('pages.goi-hien-tai', [
            'title' => 'Gói hiện tại',
            'plans' => config('plans.list', []),
            'termOptions' => config('plans.term_options', [3, 6, 12]),
            'currentPlan' => $user ? $user->plan : null,
            'planExpiresAt' => $user ? $user->plan_expires_at : null,
        ]);
    })->name('goi-hien-tai');

    Route::get('/goi-hien-tai/thanh-toan/{plan}', [\App\Http\Controllers\GoiHienTaiController::class, 'thanhToan'])->name('goi-hien-tai.thanh-toan')->where('plan', 'basic|starter|pro|team|company|corporate');
    Route::get('/goi-hien-tai/thanh-toan/check-status', [\App\Http\Controllers\GoiHienTaiController::class, 'checkStatus'])->name('goi-hien-tai.check-status');

    Route::middleware(['feature:tribeos'])->group(function () {
        Route::get('/tribeos', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $feedPosts = collect();
            $tribeosGroups = collect();
            if ($user) {
                $tribeosGroups = $user->tribeosGroups()->orderByPivot('created_at', 'desc')->get();
                $groupIds = $tribeosGroups->pluck('id');
                $query = \App\Models\TribeosPost::whereIn('tribeos_group_id', $groupIds)->with(['group', 'user', 'reactions', 'comments'])->orderByDesc('created_at');
                $filter = $request->input('filter', 'all');
                if ($filter === 'mine' && $user) {
                    $query->where('user_id', $user->id);
                } elseif (preg_match('/^group_(\d+)$/', $filter, $m)) {
                    $gid = (int) $m[1];
                    if ($groupIds->contains($gid)) {
                        $query->where('tribeos_group_id', $gid);
                    }
                }
                $feedPosts = $query->limit(50)->get();
            }
            if ($request->ajax() && $request->get('partial') === 'feed') {
                return response()->view('pages.tribeos.partials.feed-content', [
                    'feedPosts' => $feedPosts,
                    'tribeosGroups' => $tribeosGroups,
                    'currentFilter' => $filter,
                ]);
            }
            return view('pages.tribeos.index', ['title' => 'TribeOS', 'feedPosts' => $feedPosts, 'tribeosGroups' => $tribeosGroups]);
        })->name('tribeos');

        Route::get('/tribeos/groups', [\App\Http\Controllers\Tribeos\GroupController::class, 'index'])->name('tribeos.groups.index');
        Route::get('/tribeos/groups/create', [\App\Http\Controllers\Tribeos\GroupController::class, 'create'])->name('tribeos.groups.create');
        Route::post('/tribeos/groups', [\App\Http\Controllers\Tribeos\GroupController::class, 'store'])->name('tribeos.groups.store');
        Route::get('/tribeos/groups/{slug}', [\App\Http\Controllers\Tribeos\GroupController::class, 'show'])->name('tribeos.groups.show')->where('slug', '[a-z0-9\-]+');
        Route::post('/tribeos/groups/{slug}/posts', [\App\Http\Controllers\Tribeos\GroupController::class, 'storePost'])->name('tribeos.groups.posts.store')->where('slug', '[a-z0-9\-]+');
        Route::post('/tribeos/groups/{slug}/posts/{post}/comments', [\App\Http\Controllers\Tribeos\GroupController::class, 'storeComment'])->name('tribeos.groups.posts.comments.store')->where('slug', '[a-z0-9\-]+');
        Route::post('/tribeos/groups/{slug}/posts/{post}/reaction', [\App\Http\Controllers\Tribeos\GroupController::class, 'toggleReaction'])->name('tribeos.groups.posts.reaction')->where('slug', '[a-z0-9\-]+');
        Route::get('/tribeos/groups/{slug}/invite', [\App\Http\Controllers\Tribeos\GroupController::class, 'invite'])->name('tribeos.groups.invite')->where('slug', '[a-z0-9\-]+');
        Route::get('/tribeos/groups/{slug}/search-users', [\App\Http\Controllers\Tribeos\GroupController::class, 'searchUsers'])->name('tribeos.groups.search-users')->where('slug', '[a-z0-9\-]+');
        Route::post('/tribeos/groups/{slug}/invite', [\App\Http\Controllers\Tribeos\GroupController::class, 'storeInvite'])->name('tribeos.groups.invite.store')->where('slug', '[a-z0-9\-]+');
        Route::put('/tribeos/groups/{slug}/members/{member}', [\App\Http\Controllers\Tribeos\GroupController::class, 'updateMemberRole'])->name('tribeos.groups.members.update-role')->where('slug', '[a-z0-9\-]+');
        Route::post('/tribeos/groups/{slug}/leave', [\App\Http\Controllers\Tribeos\GroupController::class, 'leave'])->name('tribeos.groups.leave')->where('slug', '[a-z0-9\-]+');
        Route::get('/tribeos/invitations', [\App\Http\Controllers\Tribeos\InvitationController::class, 'index'])->name('tribeos.invitations.index');
        Route::post('/tribeos/invitations/{id}/accept', [\App\Http\Controllers\Tribeos\InvitationController::class, 'accept'])->name('tribeos.invitations.accept');
        Route::post('/tribeos/invitations/{id}/reject', [\App\Http\Controllers\Tribeos\InvitationController::class, 'reject'])->name('tribeos.invitations.reject');
    });

    Route::middleware(['feature:tai_chinh'])->group(function () {
        Route::get('/tai-chinh', [\App\Http\Controllers\TaiChinhController::class, 'index'])->name('tai-chinh');
        Route::get('/tai-chinh/su-kien', [\App\Http\Controllers\TaiChinhController::class, 'suKien'])->name('tai-chinh.su-kien');
        Route::post('/tai-chinh/event-acknowledge', [\App\Http\Controllers\TaiChinhController::class, 'acknowledgeEvent'])->name('tai-chinh.event-acknowledge');
        Route::post('/tai-chinh/settings/low-balance-threshold', [\App\Http\Controllers\TaiChinhController::class, 'updateLowBalanceThreshold'])->name('tai-chinh.settings.low-balance-threshold');
        Route::get('/tai-chinh/projection', [\App\Http\Controllers\TaiChinhController::class, 'projection'])->name('tai-chinh.projection');
        Route::get('/tai-chinh/insight-payload', [\App\Http\Controllers\TaiChinhController::class, 'insightPayload'])->name('tai-chinh.insight-payload');
        Route::post('/tai-chinh/insight-feedback', [\App\Http\Controllers\TaiChinhController::class, 'storeInsightFeedback'])->name('tai-chinh.insight-feedback');
        Route::post('/tai-chinh/nguong-ngan-sach', [\App\Http\Controllers\TaiChinh\BudgetThresholdController::class, 'storeBudgetThreshold'])->name('tai-chinh.nguong-ngan-sach.store');
        Route::get('/tai-chinh/nguong-ngan-sach/{id}/edit', [\App\Http\Controllers\TaiChinh\BudgetThresholdController::class, 'editBudgetThresholdJson'])->name('tai-chinh.nguong-ngan-sach.edit-json');
        Route::put('/tai-chinh/nguong-ngan-sach/{id}', [\App\Http\Controllers\TaiChinh\BudgetThresholdController::class, 'updateBudgetThreshold'])->name('tai-chinh.nguong-ngan-sach.update');
        Route::delete('/tai-chinh/nguong-ngan-sach/{id}', [\App\Http\Controllers\TaiChinh\BudgetThresholdController::class, 'destroyBudgetThreshold'])->name('tai-chinh.nguong-ngan-sach.destroy');
        Route::get('/tai-chinh/nguong-ngan-sach-table', [\App\Http\Controllers\TaiChinh\BudgetThresholdController::class, 'nguongNganSachTable'])->name('tai-chinh.nguong-ngan-sach-table');
        Route::post('/tai-chinh/muc-tieu-thu', [\App\Http\Controllers\TaiChinh\IncomeGoalController::class, 'storeIncomeGoal'])->name('tai-chinh.muc-tieu-thu.store');
        Route::get('/tai-chinh/muc-tieu-thu/{id}/edit', [\App\Http\Controllers\TaiChinh\IncomeGoalController::class, 'editIncomeGoalJson'])->name('tai-chinh.muc-tieu-thu.edit-json');
        Route::put('/tai-chinh/muc-tieu-thu/{id}', [\App\Http\Controllers\TaiChinh\IncomeGoalController::class, 'updateIncomeGoal'])->name('tai-chinh.muc-tieu-thu.update');
        Route::delete('/tai-chinh/muc-tieu-thu/{id}', [\App\Http\Controllers\TaiChinh\IncomeGoalController::class, 'destroyIncomeGoal'])->name('tai-chinh.muc-tieu-thu.destroy');
        Route::get('/tai-chinh/muc-tieu-thu-table', [\App\Http\Controllers\TaiChinh\IncomeGoalController::class, 'mucTieuThuTable'])->name('tai-chinh.muc-tieu-thu-table');
        Route::get('/tai-chinh/giao-dich-table', [\App\Http\Controllers\TaiChinh\GiaoDichController::class, 'giaoDichTable'])->name('tai-chinh.giao-dich-table');
        Route::post('/tai-chinh/confirm-classification', [\App\Http\Controllers\TaiChinh\GiaoDichController::class, 'confirmClassification'])->name('tai-chinh.confirm-classification');
        Route::post('/tai-chinh/danh-muc', [\App\Http\Controllers\TaiChinh\UserCategoryController::class, 'store'])->name('tai-chinh.danh-muc.store');
        Route::put('/tai-chinh/danh-muc/{id}', [\App\Http\Controllers\TaiChinh\UserCategoryController::class, 'update'])->name('tai-chinh.danh-muc.update');
        Route::delete('/tai-chinh/danh-muc/{id}', [\App\Http\Controllers\TaiChinh\UserCategoryController::class, 'destroy'])->name('tai-chinh.danh-muc.destroy');
        Route::post('/tai-chinh/tai-khoan', [\App\Http\Controllers\TaiChinh\BankAccountController::class, 'store'])->name('tai-chinh.tai-khoan.store');
        Route::post('/tai-chinh/tai-khoan/cap-nhat-so-du', [\App\Http\Controllers\TaiChinh\BankAccountController::class, 'updateAccountBalance'])->name('tai-chinh.tai-khoan.update-balance');
        Route::post('/tai-chinh/tai-khoan/unlink', [\App\Http\Controllers\TaiChinh\BankAccountController::class, 'unlink'])->name('tai-chinh.tai-khoan.unlink');
        Route::get('/tai-chinh/liability/create', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'create'])->name('tai-chinh.liability.create');
        Route::get('/tai-chinh/liability/{id}/thanh-toan', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'thanhToan'])->name('tai-chinh.liability.thanh-toan');
        Route::get('/tai-chinh/liability/{id}/ghi-lai', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'ghiLai'])->name('tai-chinh.liability.ghi-lai');
        Route::get('/tai-chinh/liability/{id}', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'show'])->name('tai-chinh.liability.show');
        Route::post('/tai-chinh/liability', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'store'])->name('tai-chinh.liability.store');
        Route::put('/tai-chinh/liability/{id}', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'update'])->name('tai-chinh.liability.update');
        Route::post('/tai-chinh/liability/{id}/close', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'close'])->name('tai-chinh.liability.close');
        Route::delete('/tai-chinh/liability/{id}', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'destroy'])->name('tai-chinh.liability.destroy');
        Route::post('/tai-chinh/liability/{id}/payment', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'storePayment'])->name('tai-chinh.liability.payment.store');
        Route::post('/tai-chinh/liability/{id}/accrual', [\App\Http\Controllers\TaiChinh\LiabilityController::class, 'storeAccrual'])->name('tai-chinh.liability.accrual.store');
        Route::get('/tai-chinh/loans', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'index'])->name('tai-chinh.loans.index');
        Route::get('/tai-chinh/loans/create', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'create'])->name('tai-chinh.loans.create');
        Route::post('/tai-chinh/loans', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'store'])->name('tai-chinh.loans.store');
        Route::get('/tai-chinh/loans/{id}', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'show'])->name('tai-chinh.loans.show');
        Route::post('/tai-chinh/loans/{id}/accept', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'accept'])->name('tai-chinh.loans.accept');
        Route::get('/tai-chinh/loans/{id}/payment', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'payment'])->name('tai-chinh.loans.payment');
        Route::post('/tai-chinh/loans/{id}/payment', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'storePayment'])->name('tai-chinh.loans.payment.store');
        Route::post('/tai-chinh/loans/{id}/close', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'close'])->name('tai-chinh.loans.close');
        Route::delete('/tai-chinh/loans/{id}', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'destroy'])->name('tai-chinh.loans.destroy');
        Route::post('/tai-chinh/loans/pending/{id}/record', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'recordPendingPayment'])->name('tai-chinh.loans.pending.record');
        Route::post('/tai-chinh/loans/pending/{id}/confirm', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'confirmPendingPayment'])->name('tai-chinh.loans.pending.confirm');
        Route::post('/tai-chinh/loans/{id}/ledger/{entryId}/confirm', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'confirmPaymentEntry'])->name('tai-chinh.loans.ledger.confirm');
        Route::post('/tai-chinh/loans/{id}/ledger/{entryId}/reject', [\App\Http\Controllers\TaiChinh\LoanContractController::class, 'rejectPaymentEntry'])->name('tai-chinh.loans.ledger.reject');
    });

    Route::middleware(['feature:cong_viec'])->group(function () {
        Route::get('/cong-viec', [\App\Http\Controllers\CongViecController::class, 'index'])->name('cong-viec');
        Route::get('/cong-viec/tasks/{id}/edit', [\App\Http\Controllers\CongViecController::class, 'edit'])->name('cong-viec.tasks.edit');
        Route::post('/cong-viec/tasks', [\App\Http\Controllers\CongViecController::class, 'store'])->name('cong-viec.tasks.store');
        Route::put('/cong-viec/tasks/{id}', [\App\Http\Controllers\CongViecController::class, 'update'])->name('cong-viec.tasks.update');
        Route::delete('/cong-viec/tasks/{id}', [\App\Http\Controllers\CongViecController::class, 'destroy'])->name('cong-viec.tasks.destroy');
        Route::post('/cong-viec/labels', [\App\Http\Controllers\CongViecController::class, 'storeLabel'])->name('cong-viec.labels.store');
        Route::post('/cong-viec/projects', [\App\Http\Controllers\CongViecController::class, 'storeProject'])->name('cong-viec.projects.store');
        Route::patch('/cong-viec/tasks/{id}/toggle-complete', [\App\Http\Controllers\CongViecController::class, 'toggleComplete'])->name('cong-viec.tasks.toggle-complete');
        Route::post('/cong-viec/tasks/{id}/confirm-complete', [\App\Http\Controllers\CongViecController::class, 'confirmComplete'])->name('cong-viec.tasks.confirm-complete');
        Route::patch('/cong-viec/tasks/{id}/kanban-status', [\App\Http\Controllers\CongViecController::class, 'updateKanbanStatus'])->name('cong-viec.tasks.kanban-status');
        Route::post('/cong-viec/kanban-columns', [\App\Http\Controllers\CongViecController::class, 'storeKanbanColumn'])->name('cong-viec.kanban-columns.store');
        Route::patch('/cong-viec/kanban-columns/{id}', [\App\Http\Controllers\CongViecController::class, 'updateKanbanColumn'])->name('cong-viec.kanban-columns.update');
        Route::get('/cong-viec/behavior-baseline', [\App\Http\Controllers\CongViec\BehaviorBaselineController::class, 'edit'])->name('cong-viec.behavior-baseline.edit');
        Route::match(['put', 'post'], '/cong-viec/behavior-baseline', [\App\Http\Controllers\CongViec\BehaviorBaselineController::class, 'update'])->name('cong-viec.behavior-baseline.update');
        Route::post('/cong-viec/behavior-events', [\App\Http\Controllers\CongViec\BehaviorEventController::class, 'store'])->name('cong-viec.behavior-events.store');
        Route::get('/cong-viec/programs', [\App\Http\Controllers\CongViec\BehaviorProgramController::class, 'index'])->name('cong-viec.programs.index');
        Route::get('/cong-viec/programs/create', [\App\Http\Controllers\CongViec\BehaviorProgramController::class, 'create'])->name('cong-viec.programs.create');
        Route::post('/cong-viec/programs', [\App\Http\Controllers\CongViec\BehaviorProgramController::class, 'store'])->name('cong-viec.programs.store');
        Route::get('/cong-viec/programs/{id}', [\App\Http\Controllers\CongViec\BehaviorProgramController::class, 'show'])->name('cong-viec.programs.show');
    });

    Route::middleware(['feature:thu_chi'])->group(function () {
        Route::get('/thu-chi', [\App\Http\Controllers\ThuChiController::class, 'index'])->name('thu-chi');
        Route::post('/thu-chi/income', [\App\Http\Controllers\ThuChiController::class, 'storeIncome'])->name('thu-chi.income.store');
        Route::get('/thu-chi/income/{id}/edit', [\App\Http\Controllers\ThuChiController::class, 'editIncome'])->name('thu-chi.income.edit');
        Route::put('/thu-chi/income/{id}', [\App\Http\Controllers\ThuChiController::class, 'updateIncome'])->name('thu-chi.income.update');
        Route::delete('/thu-chi/income/{id}', [\App\Http\Controllers\ThuChiController::class, 'destroyIncome'])->name('thu-chi.income.destroy');
        Route::post('/thu-chi/expense', [\App\Http\Controllers\ThuChiController::class, 'storeExpense'])->name('thu-chi.expense.store');
        Route::get('/thu-chi/expense/{id}/edit', [\App\Http\Controllers\ThuChiController::class, 'editExpense'])->name('thu-chi.expense.edit');
        Route::put('/thu-chi/expense/{id}', [\App\Http\Controllers\ThuChiController::class, 'updateExpense'])->name('thu-chi.expense.update');
        Route::delete('/thu-chi/expense/{id}', [\App\Http\Controllers\ThuChiController::class, 'destroyExpense'])->name('thu-chi.expense.destroy');
        Route::post('/thu-chi/sources', [\App\Http\Controllers\ThuChiController::class, 'storeSource'])->name('thu-chi.sources.store');
        Route::post('/thu-chi/recurring-templates', [\App\Http\Controllers\ThuChiController::class, 'storeRecurringTemplate'])->name('thu-chi.recurring.store');
        Route::post('/thu-chi/recurring-templates/{id}/toggle', [\App\Http\Controllers\ThuChiController::class, 'toggleRecurringTemplate'])->name('thu-chi.recurring.toggle');
        Route::delete('/thu-chi/recurring-templates/{id}', [\App\Http\Controllers\ThuChiController::class, 'destroyRecurringTemplate'])->name('thu-chi.recurring.destroy');
    });

    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');

    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');

    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Blank']);
    })->name('blank');

    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');

    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badges']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');

    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Videos']);
    })->name('videos');
});
