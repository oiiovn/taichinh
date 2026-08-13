<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Luồng: Giao diện chính (sau đăng nhập)
| Middleware: auth
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationUnreadCountController::class, 'index'])->name('notifications.unread-count');
    Route::get('/notifications/dropdown-data', [\App\Http\Controllers\NotificationUnreadCountController::class, 'dropdownData'])->name('notifications.dropdown-data');

    Route::get('/thong-bao', [\App\Http\Controllers\BroadcastViewController::class, 'index'])->name('thong-bao.index');
    Route::get('/thong-bao/{broadcast}', [\App\Http\Controllers\BroadcastViewController::class, 'show'])->name('thong-bao.show');
    Route::post('/thong-bao/{broadcast}/mark-read', [\App\Http\Controllers\BroadcastViewController::class, 'markRead'])->name('thong-bao.mark-read');

    // Trang chủ điều hướng theo quyền user
    Route::get('/', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user) {
            if (! $user->is_admin && method_exists($user, 'canManageFoodThongKeBuff') && $user->canManageFoodThongKeBuff()) {
                return redirect()->route('food.thong-ke-buff');
            }
            if (method_exists($user, 'canCreateFoodBuffOrder') && $user->canCreateFoodBuffOrder()) {
                return redirect()->route('food.dat-don');
            }
            if (method_exists($user, 'isFoodReviewsOnlyUser') && $user->isFoodReviewsOnlyUser()) {
                return redirect()->route('food.reviews.index');
            }
            if (method_exists($user, 'canManageAnyFood') && $user->canManageAnyFood()) {
                return redirect()->route('food');
            }
            if (method_exists($user, 'canUseFeature') && $user->canUseFeature('tai_chinh')) {
                return redirect()->route('tai-chinh', ['tab' => 'chien-luoc']);
            }
        }

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
        if ($user) {
            $user->refresh();
        }

        return view('pages.goi-hien-tai', [
            'title' => 'Gói hiện tại',
            'plans' => \App\Models\PlanConfig::getList(),
            'termOptions' => \App\Models\PlanConfig::getTermOptions(),
            'currentPlan' => $user && $user->plan ? strtolower((string) $user->plan) : null,
            'planExpiresAt' => $user ? $user->plan_expires_at : null,
        ]);
    })->name('goi-hien-tai');

    Route::get('/goi-hien-tai/thanh-toan/{plan}', [\App\Http\Controllers\GoiHienTaiController::class, 'thanhToan'])->name('goi-hien-tai.thanh-toan')->where('plan', 'basic|starter|pro|team|company|corporate');
    Route::get('/goi-hien-tai/thanh-toan/check-status', [\App\Http\Controllers\GoiHienTaiController::class, 'checkStatus'])->name('goi-hien-tai.check-status');

    Route::middleware(['feature:tribeos'])->group(function () {
        Route::get('/tribeos', [\App\Http\Controllers\Tribeos\HomeController::class, 'index'])->name('tribeos');

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
        Route::post('/tai-chinh/lich-thanh-toan', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'store'])->name('tai-chinh.payment-schedules.store');
        Route::get('/tai-chinh/lich-thanh-toan/{id}/edit', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'edit'])->name('tai-chinh.payment-schedules.edit');
        Route::put('/tai-chinh/lich-thanh-toan/{id}', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'update'])->name('tai-chinh.payment-schedules.update');
        Route::delete('/tai-chinh/lich-thanh-toan/{id}', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'destroy'])->name('tai-chinh.payment-schedules.destroy');
        Route::get('/tai-chinh/lich-thanh-toan/{id}/task-payload', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'taskPayload'])->name('tai-chinh.payment-schedules.task-payload');
        Route::post('/tai-chinh/lich-thanh-toan/{id}/create-task', [\App\Http\Controllers\TaiChinh\PaymentScheduleController::class, 'createTask'])->name('tai-chinh.payment-schedules.create-task');
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
        Route::get('/tai-chinh/nhom-gia-dinh', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'index'])->name('tai-chinh.nhom-gia-dinh.index');
        Route::post('/tai-chinh/nhom-gia-dinh', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'store'])->name('tai-chinh.nhom-gia-dinh.store');
        Route::get('/tai-chinh/nhom-gia-dinh/{id}', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'show'])->name('tai-chinh.nhom-gia-dinh.show');
        Route::post('/tai-chinh/nhom-gia-dinh/{id}/members', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'storeMember'])->name('tai-chinh.nhom-gia-dinh.members.store');
        Route::get('/tai-chinh/nhom-gia-dinh/{id}/giao-dich-table', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'giaoDichTable'])->name('tai-chinh.nhom-gia-dinh.giao-dich-table');
        Route::post('/tai-chinh/nhom-gia-dinh/{household}/transactions/{transaction}/depositor', [\App\Http\Controllers\TaiChinh\HouseholdController::class, 'updateTransactionDepositor'])->name('tai-chinh.nhom-gia-dinh.transactions.depositor');
        Route::post('/tai-chinh/tongquan-statistic', [\App\Http\Controllers\TaiChinh\TongquanStatisticController::class, 'store'])->name('tai-chinh.tongquan-statistic.store');
        Route::put('/tai-chinh/tongquan-statistic/{tongquanStatistic}', [\App\Http\Controllers\TaiChinh\TongquanStatisticController::class, 'update'])->name('tai-chinh.tongquan-statistic.update');
        Route::delete('/tai-chinh/tongquan-statistic/{tongquanStatistic}', [\App\Http\Controllers\TaiChinh\TongquanStatisticController::class, 'destroy'])->name('tai-chinh.tongquan-statistic.destroy');
    });

    Route::middleware(['feature:cong_viec'])->group(function () {
        Route::get('/cong-viec', [\App\Http\Controllers\CongViecController::class, 'index'])->name('cong-viec');
        Route::get('/cong-viec/from-schedule-payload', [\App\Http\Controllers\CongViecController::class, 'fromSchedulePayload'])->name('cong-viec.from-schedule-payload');
        Route::get('/cong-viec/tasks/similar', [\App\Http\Controllers\CongViecController::class, 'similarTasks'])->name('cong-viec.tasks.similar');
        Route::get('/cong-viec/tasks/{id}', [\App\Http\Controllers\CongViecController::class, 'show'])->name('cong-viec.tasks.show');
        Route::get('/cong-viec/tasks/{id}/edit', [\App\Http\Controllers\CongViecController::class, 'edit'])->name('cong-viec.tasks.edit');
        Route::get('/cong-viec/tasks/{id}/edit-data', [\App\Http\Controllers\CongViecController::class, 'editData'])->name('cong-viec.tasks.edit-data');
        Route::post('/cong-viec/tasks', [\App\Http\Controllers\CongViecController::class, 'store'])->name('cong-viec.tasks.store');
        Route::put('/cong-viec/tasks/{id}', [\App\Http\Controllers\CongViecController::class, 'update'])->name('cong-viec.tasks.update');
        Route::delete('/cong-viec/tasks/{id}', [\App\Http\Controllers\CongViecController::class, 'destroy'])->name('cong-viec.tasks.destroy');
        Route::post('/cong-viec/labels', [\App\Http\Controllers\CongViecController::class, 'storeLabel'])->name('cong-viec.labels.store');
        Route::post('/cong-viec/projects', [\App\Http\Controllers\CongViecController::class, 'storeProject'])->name('cong-viec.projects.store');
        Route::patch('/cong-viec/tasks/{id}/toggle-complete', [\App\Http\Controllers\CongViecController::class, 'toggleComplete'])->name('cong-viec.tasks.toggle-complete');
        Route::patch('/cong-viec/instances/{id}/toggle-complete', [\App\Http\Controllers\CongViecController::class, 'toggleInstanceComplete'])->name('cong-viec.instances.toggle-complete');
        Route::post('/cong-viec/instances/{id}/confirm-complete', [\App\Http\Controllers\CongViecController::class, 'confirmInstanceComplete'])->name('cong-viec.instances.confirm-complete');
        Route::post('/cong-viec/focus/start/{instance}', [\App\Http\Controllers\CongViecController::class, 'focusStart'])->name('cong-viec.focus.start');
        Route::post('/cong-viec/focus/stop', [\App\Http\Controllers\CongViecController::class, 'focusStop'])->name('cong-viec.focus.stop');
        Route::post('/cong-viec/focus/activity', [\App\Http\Controllers\CongViecController::class, 'focusActivity'])->name('cong-viec.focus.activity');
        Route::patch('/cong-viec/instances/{id}/actual-duration', [\App\Http\Controllers\CongViecController::class, 'patchInstanceActualDuration'])->name('cong-viec.instances.actual-duration');
        Route::post('/cong-viec/focus/break/start', [\App\Http\Controllers\CongViecController::class, 'focusBreakStart'])->name('cong-viec.focus.break.start');
        Route::post('/cong-viec/tasks/{id}/confirm-complete', [\App\Http\Controllers\CongViecController::class, 'confirmComplete'])->name('cong-viec.tasks.confirm-complete');
        Route::patch('/cong-viec/tasks/{id}/estimated-duration', [\App\Http\Controllers\CongViecController::class, 'patchEstimatedDuration'])->name('cong-viec.tasks.estimated-duration');
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

    Route::middleware(['feature:food', 'food.restrict.qr.only'])->group(function () {
        Route::get('/food', [\App\Http\Controllers\Food\FoodController::class, 'index'])->name('food');
        Route::get('/food/qr-cham-cong/do', [\App\Http\Controllers\Food\QrChamCongController::class, 'do'])->name('food.qr-cham-cong.do');
        // Không dùng food.bao_cao: người được gán công nợ (debtor) vẫn xem được; quyền kiểm tra trong controller.
        Route::get('/food/bao-cao-ban-hang/{id}', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'show'])->name('food.bao-cao-ban-hang.show');
        Route::get('/food/cong-no', [\App\Http\Controllers\Food\CongNoController::class, 'index'])->name('food.cong-no');
        Route::post('/food/cong-no/debt/{debt}/thanh-toan-tien-mat', [\App\Http\Controllers\Food\CongNoController::class, 'storeThanhToanTienMat'])->name('food.cong-no.thanh-toan-tien-mat');

        $hasFoodEmployeeControllers = class_exists(\App\Http\Controllers\Food\ChamCongController::class)
            && class_exists(\App\Http\Controllers\Food\PayrollController::class)
            && class_exists(\App\Http\Controllers\Food\LeaveRequestController::class)
            && class_exists(\App\Http\Controllers\Food\SalaryAdvanceController::class);
        if ($hasFoodEmployeeControllers) {
            Route::get('/food/cham-cong', [\App\Http\Controllers\Food\ChamCongController::class, 'index'])->name('food.cham-cong');
            Route::post('/food/cham-cong', [\App\Http\Controllers\Food\ChamCongController::class, 'store'])->name('food.cham-cong.store');
            Route::post('/food/cham-cong/ngay-sale', [\App\Http\Controllers\Food\ChamCongController::class, 'storeSaleDay'])->name('food.cham-cong.sale-days.store');
            Route::delete('/food/cham-cong/ngay-sale/{saleDay}', [\App\Http\Controllers\Food\ChamCongController::class, 'destroySaleDay'])->name('food.cham-cong.sale-days.destroy');
            Route::post('/food/cham-cong/manual', [\App\Http\Controllers\Food\ChamCongController::class, 'storeManual'])->name('food.cham-cong.store-manual');
            Route::put('/food/cham-cong/{log}', [\App\Http\Controllers\Food\ChamCongController::class, 'update'])->name('food.cham-cong.update');
            Route::delete('/food/cham-cong/{log}', [\App\Http\Controllers\Food\ChamCongController::class, 'destroy'])->name('food.cham-cong.destroy');
            Route::get('/food/luong-cua-toi', [\App\Http\Controllers\Food\PayrollController::class, 'myPayroll'])->name('food.luong-cua-toi');
            Route::get('/food/xin-nghi', [\App\Http\Controllers\Food\LeaveRequestController::class, 'index'])->name('food.xin-nghi');
            Route::post('/food/xin-nghi', [\App\Http\Controllers\Food\LeaveRequestController::class, 'store'])->name('food.xin-nghi.store');
            Route::post('/food/xin-nghi/{xinNghi}/approve', [\App\Http\Controllers\Food\LeaveRequestController::class, 'approve'])->name('food.xin-nghi.approve');
            Route::post('/food/xin-nghi/{xinNghi}/reject', [\App\Http\Controllers\Food\LeaveRequestController::class, 'reject'])->name('food.xin-nghi.reject');
            Route::get('/food/ung-luong', [\App\Http\Controllers\Food\SalaryAdvanceController::class, 'index'])->name('food.ung-luong');
            Route::post('/food/ung-luong', [\App\Http\Controllers\Food\SalaryAdvanceController::class, 'store'])->name('food.ung-luong.store');
            Route::post('/food/ung-luong/{ungLuong}/approve', [\App\Http\Controllers\Food\SalaryAdvanceController::class, 'approve'])->name('food.ung-luong.approve');
            Route::post('/food/ung-luong/{ungLuong}/reject', [\App\Http\Controllers\Food\SalaryAdvanceController::class, 'reject'])->name('food.ung-luong.reject');
            Route::post('/food/ung-luong/{ungLuong}/paid', [\App\Http\Controllers\Food\SalaryAdvanceController::class, 'markPaid'])->name('food.ung-luong.paid');
        }
        if ($hasFoodEmployeeControllers) {
            Route::get('/food/luong', [\App\Http\Controllers\Food\PayrollController::class, 'index'])->name('food.luong');
            Route::post('/food/luong/ghi-thanh-toan', [\App\Http\Controllers\Food\PayrollController::class, 'storePayment'])->name('food.luong.store-payment');
            Route::put('/food/luong/thanh-toan/{payment}', [\App\Http\Controllers\Food\PayrollController::class, 'updatePayment'])->name('food.luong.update-payment');
            Route::delete('/food/luong/thanh-toan/{payment}', [\App\Http\Controllers\Food\PayrollController::class, 'destroyPayment'])->name('food.luong.destroy-payment');
        }
        if ($hasFoodEmployeeControllers && class_exists(\App\Http\Controllers\Food\NhanVienController::class)) {
            Route::middleware(['food.employee.manager'])->group(function () {
                Route::get('/food/nhan-vien', [\App\Http\Controllers\Food\NhanVienController::class, 'index'])->name('food.nhan-vien');
                Route::get('/food/nhan-vien/create', [\App\Http\Controllers\Food\NhanVienController::class, 'create'])->name('food.nhan-vien.create');
                Route::post('/food/nhan-vien', [\App\Http\Controllers\Food\NhanVienController::class, 'store'])->name('food.nhan-vien.store');
                Route::get('/food/nhan-vien/{nhanVien}/edit', [\App\Http\Controllers\Food\NhanVienController::class, 'edit'])->name('food.nhan-vien.edit');
                Route::put('/food/nhan-vien/{nhanVien}', [\App\Http\Controllers\Food\NhanVienController::class, 'update'])->name('food.nhan-vien.update');
                Route::delete('/food/nhan-vien/{nhanVien}', [\App\Http\Controllers\Food\NhanVienController::class, 'destroy'])->name('food.nhan-vien.destroy');
            });
        }

        Route::middleware(['food.reviews'])->group(function () {
            Route::get('/food/danh-gia', [\App\Http\Controllers\Food\FoodReviewController::class, 'index'])->name('food.reviews.index');
            Route::get('/food/danh-gia/lich-su-nhan-qua', [\App\Http\Controllers\Food\FoodReviewController::class, 'giftAttempts'])->name('food.reviews.gift-attempts');
            Route::get('/food/danh-gia/import', [\App\Http\Controllers\Food\FoodReviewController::class, 'showImport'])->name('food.reviews.import');
            Route::post('/food/danh-gia/import-text', [\App\Http\Controllers\Food\FoodReviewController::class, 'importText'])->name('food.reviews.import-text');
            Route::post('/food/danh-gia/{review}/mark-rewarded', [\App\Http\Controllers\Food\FoodReviewController::class, 'markRewarded'])->name('food.reviews.mark-rewarded');
            Route::post('/food/danh-gia/{review}/unmark-rewarded', [\App\Http\Controllers\Food\FoodReviewController::class, 'unmarkRewarded'])->name('food.reviews.unmark-rewarded');
        });

        Route::middleware(['food.thong_ke_buff'])->group(function () {
            Route::get('/food/lich-dat-don', [\App\Http\Controllers\Food\FoodBuffController::class, 'orderSchedulePage'])->name('food.lich-dat-don');
            Route::post('/food/lich-dat-don/xac-nhan', [\App\Http\Controllers\Food\FoodBuffController::class, 'acknowledgeOrderSchedules'])->name('food.lich-dat-don.acknowledge');
            Route::post('/food/lich-dat-don', [\App\Http\Controllers\Food\FoodBuffController::class, 'storeOrderSchedule'])->name('food.lich-dat-don.store');
            Route::delete('/food/lich-dat-don/{foodBuffOrderSchedule}', [\App\Http\Controllers\Food\FoodBuffController::class, 'destroyOrderSchedule'])->name('food.lich-dat-don.destroy');
            Route::get('/food/thong-ke-buff', [\App\Http\Controllers\Food\FoodBuffController::class, 'index'])->name('food.thong-ke-buff');
            Route::post('/food/thong-ke-buff/thanh-toan-tien-cong', [\App\Http\Controllers\Food\FoodBuffController::class, 'storeLaborCashPayment'])->name('food.thong-ke-buff.thanh-toan-tien-cong');
            Route::patch('/food/thong-ke-buff/don/{foodBuffOrder}/danh-gia', [\App\Http\Controllers\Food\FoodBuffController::class, 'toggleCustomerReviewed'])->name('food.thong-ke-buff.order.reviewed');
            Route::delete('/food/thong-ke-buff/don/{foodBuffOrder}', [\App\Http\Controllers\Food\FoodBuffController::class, 'destroyBuffOrder'])->name('food.thong-ke-buff.order.destroy');
        });

        Route::middleware(['food.buff_order'])->group(function () {
            Route::get('/food/dat-don', [\App\Http\Controllers\Food\FoodBuffController::class, 'datDonPage'])->name('food.dat-don');
            Route::get('/food/lich-da-xac-nhan', [\App\Http\Controllers\Food\FoodBuffController::class, 'confirmedSchedulesPage'])->name('food.lich-da-xac-nhan');
            Route::post('/food/dat-don', [\App\Http\Controllers\Food\FoodBuffController::class, 'storeDatDon'])->name('food.dat-don.store');
            Route::delete('/food/dat-don/{foodBuffOrder}', [\App\Http\Controllers\Food\FoodBuffController::class, 'destroyDatDon'])->name('food.dat-don.destroy');
        });

        Route::middleware(['food.bao_cao'])->group(function () {
            Route::get('/food/chi-nhanh', [\App\Http\Controllers\Food\FoodChiNhanhController::class, 'index'])->name('food.chi-nhanh');
            Route::post('/food/chi-nhanh', [\App\Http\Controllers\Food\FoodChiNhanhController::class, 'store'])->name('food.chi-nhanh.store');
            Route::put('/food/chi-nhanh/{branch}', [\App\Http\Controllers\Food\FoodChiNhanhController::class, 'update'])->name('food.chi-nhanh.update');
            Route::delete('/food/chi-nhanh/{branch}', [\App\Http\Controllers\Food\FoodChiNhanhController::class, 'destroy'])->name('food.chi-nhanh.destroy');
            Route::get('/food/khach-hang', [\App\Http\Controllers\Food\KhachHangController::class, 'index'])->name('food.khach-hang');
            Route::get('/food/bao-cao-ban-hang', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'index'])->name('food.bao-cao-ban-hang');
            Route::post('/food/bao-cao-ban-hang', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'store'])->name('food.bao-cao-ban-hang.store');
            Route::put('/food/bao-cao-ban-hang/{id}', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'update'])->name('food.bao-cao-ban-hang.update');
            Route::post('/food/bao-cao-ban-hang/{id}/cong-no', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'storeCongNo'])->name('food.bao-cao-ban-hang.cong-no.store');
            Route::post('/food/bao-cao-ban-hang/{id}/tieu-hao-nl', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'applyMaterialConsumption'])->name('food.bao-cao-ban-hang.tieu-hao');
            Route::delete('/food/bao-cao-ban-hang/{id}', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'destroy'])->name('food.bao-cao-ban-hang.destroy');
            Route::put('/food/bao-cao-ban-hang/{id}/doanh-so', [\App\Http\Controllers\Food\BaoCaoBanHangController::class, 'updateDoanhSo'])->name('food.bao-cao-ban-hang.update-doanh-so');
        });
        Route::middleware(['food.san_pham'])->group(function () {
            Route::get('/food/mon', [\App\Http\Controllers\Food\MonController::class, 'index'])->name('food.mon');
            Route::get('/food/san-pham', [\App\Http\Controllers\Food\SanPhamController::class, 'index'])->name('food.san-pham');
            Route::post('/food/san-pham/paste', [\App\Http\Controllers\Food\SanPhamController::class, 'pasteFromSheet'])->name('food.san-pham.paste');
            Route::post('/food/san-pham', [\App\Http\Controllers\Food\SanPhamController::class, 'store'])->name('food.san-pham.store');
            Route::put('/food/san-pham/{id}', [\App\Http\Controllers\Food\SanPhamController::class, 'update'])->name('food.san-pham.update');
            Route::post('/food/san-pham/bulk-gia-von', [\App\Http\Controllers\Food\SanPhamController::class, 'bulkGiaVon'])->name('food.san-pham.bulk-gia-von');
            Route::post('/food/san-pham/bulk-destroy', [\App\Http\Controllers\Food\SanPhamController::class, 'bulkDestroy'])->name('food.san-pham.bulk-destroy');
            Route::delete('/food/san-pham/{id}', [\App\Http\Controllers\Food\SanPhamController::class, 'destroy'])->name('food.san-pham.destroy');
            Route::get('/food/san-pham/{id}/cong-thuc', [\App\Http\Controllers\Food\NguyenLieuController::class, 'productRecipe'])->name('food.san-pham.cong-thuc');
            Route::post('/food/san-pham/{id}/cong-thuc', [\App\Http\Controllers\Food\NguyenLieuController::class, 'assignProductTemplate'])->name('food.san-pham.cong-thuc.assign');
            Route::post('/food/san-pham/{id}/cong-thuc/legacy', [\App\Http\Controllers\Food\NguyenLieuController::class, 'storeProductRecipe'])->name('food.san-pham.cong-thuc.store');
            Route::delete('/food/san-pham/{id}/cong-thuc/{recipe}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'destroyProductRecipe'])->name('food.san-pham.cong-thuc.destroy');

            Route::get('/food/cong-thuc', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucIndex'])->name('food.cong-thuc');
            Route::post('/food/cong-thuc', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucStore'])->name('food.cong-thuc.store');
            Route::post('/food/cong-thuc/{congThuc}/duplicate', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucDuplicate'])->name('food.cong-thuc.duplicate');
            Route::get('/food/cong-thuc/{congThuc}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucShow'])->name('food.cong-thuc.show');
            Route::put('/food/cong-thuc/{congThuc}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucUpdate'])->name('food.cong-thuc.update');
            Route::delete('/food/cong-thuc/{congThuc}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucDestroy'])->name('food.cong-thuc.destroy');
            Route::post('/food/cong-thuc/{congThuc}/items', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucStoreItem'])->name('food.cong-thuc.items.store');
            Route::delete('/food/cong-thuc/{congThuc}/items/{item}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucDestroyItem'])->name('food.cong-thuc.items.destroy');
            Route::post('/food/cong-thuc/{congThuc}/products', [\App\Http\Controllers\Food\NguyenLieuController::class, 'congThucSyncProducts'])->name('food.cong-thuc.products.sync');

            Route::get('/food/nguyen-lieu', [\App\Http\Controllers\Food\NguyenLieuController::class, 'index'])->name('food.nguyen-lieu');
            Route::get('/food/nguyen-lieu/dat-hang', [\App\Http\Controllers\Food\NguyenLieuController::class, 'datHang'])->name('food.nguyen-lieu.dat-hang');
            Route::post('/food/nguyen-lieu', [\App\Http\Controllers\Food\NguyenLieuController::class, 'store'])->name('food.nguyen-lieu.store');
            Route::put('/food/nguyen-lieu/{nguyenLieu}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'update'])->name('food.nguyen-lieu.update');
            Route::patch('/food/nguyen-lieu/{nguyenLieu}/gia-don-vi', [\App\Http\Controllers\Food\NguyenLieuController::class, 'updateUnitCost'])->name('food.nguyen-lieu.update-unit-cost');
            Route::delete('/food/nguyen-lieu/{nguyenLieu}', [\App\Http\Controllers\Food\NguyenLieuController::class, 'destroy'])->name('food.nguyen-lieu.destroy');
            Route::post('/food/nguyen-lieu/{nguyenLieu}/nhap', [\App\Http\Controllers\Food\NguyenLieuController::class, 'stockIn'])->name('food.nguyen-lieu.stock-in');
            Route::post('/food/nguyen-lieu/{nguyenLieu}/xuat', [\App\Http\Controllers\Food\NguyenLieuController::class, 'stockOut'])->name('food.nguyen-lieu.stock-out');
            Route::post('/food/nguyen-lieu/{nguyenLieu}/dieu-chinh', [\App\Http\Controllers\Food\NguyenLieuController::class, 'stockAdjust'])->name('food.nguyen-lieu.stock-adjust');
            Route::patch('/food/nguyen-lieu/{nguyenLieu}/kiem-ton', [\App\Http\Controllers\Food\NguyenLieuController::class, 'toggleStockChecked'])->name('food.nguyen-lieu.kiem-ton');
        });
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
