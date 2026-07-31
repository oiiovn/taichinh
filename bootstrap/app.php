<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'admin.gate' => \App\Http\Middleware\EnsureAdminGateUnlocked::class,
            'feature' => \App\Http\Middleware\EnsureFeatureAllowed::class,
            'food.employee.manager' => \App\Http\Middleware\EnsureUserCanManageFoodEmployees::class,
            'food.bao_cao' => \App\Http\Middleware\EnsureUserCanManageFoodBaoCao::class,
            'food.thong_ke_buff' => \App\Http\Middleware\EnsureUserCanManageFoodThongKeBuff::class,
            'food.buff_order' => \App\Http\Middleware\EnsureUserCanCreateFoodBuffOrder::class,
            'food.reviews' => \App\Http\Middleware\EnsureUserCanManageFoodReviews::class,
            'food.san_pham' => \App\Http\Middleware\EnsureUserCanManageFoodSanPham::class,
            'food.restrict.qr.only' => \App\Http\Middleware\RestrictQrChamCongOnlyUser::class,
            'food.mobile.employee' => \App\Http\Middleware\EnsureFoodMobileEmployee::class,
            'food.mobile.qr' => \App\Http\Middleware\EnsureFoodMobileQrAttendance::class,
            'food.mobile.manager' => \App\Http\Middleware\EnsureFoodMobileManager::class,
        ]);
        $middleware->validateCsrfTokens(except: []);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Pay2s: mỗi phút chạy sync trong cùng process (tránh proc_open bị disable trên hosting)
        $schedule->call(function () {
            app(\App\Services\Pay2sApiService::class)->sync();
        })->everyMinute();
        // Tự đánh dấu 5 sao cho đơn ShopeeFood tạo thủ công sau 10 phút
        $schedule->call(function () {
            \App\Models\FoodBuffOrder::query()
                ->where(function ($q) {
                    $q->whereNull('customer_reviewed')
                        ->orWhere('customer_reviewed', false);
                })
                ->where('product_name', 'Quán Ship Bù')
                ->where('invoice_code', 'like', 'HDS%')
                ->where('created_at', '<=', now()->subMinutes(10))
                ->update([
                    'customer_reviewed' => true,
                    'review_rating' => 5,
                ]);
        })->everyMinute();
        // Recurring: phát hiện pattern định kỳ (lương, tiền nhà, subscription) — hàng ngày 2h
        $schedule->job(new \App\Jobs\DetectRecurringPatternsJob)->dailyAt('02:00');
        $schedule->job(new \App\Jobs\AccrueLiabilityInterestJob)->dailyAt('03:00');
        $schedule->job(new \App\Jobs\AccrueLoanInterestJob)->dailyAt('03:15');
        $schedule->job(new \App\Jobs\CreateLoanPendingPaymentsJob)->dailyAt('04:00');
        $schedule->command('thu-chi:recurring')->dailyAt('05:00');
        $schedule->command('behavior:compliance')->dailyAt('06:00');
        $schedule->command('forecast:learn')->dailyAt('07:00');
        $schedule->job(new \App\Jobs\BehaviorIntelligenceAggregateJob)->dailyAt('02:30');
        $schedule->job(new \App\Jobs\BehaviorPolicySyncJob)->dailyAt('03:00');
        $schedule->job(new \App\Jobs\CoachingEffectivenessOutcomeJob)->dailyAt('04:00');
        // Làm ấm cache view Tài chính (SWR) mỗi 15 phút — tránh mất dữ liệu tab Chiến lược khi cache hết hạn
        $schedule->command('tai-chinh:warm-view')->everyFifteenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\Food\AttendanceException $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => ['code' => $e->errorCode],
            ], $e->httpStatus);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 403 || $request->expectsJson() || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
                return null;
            }

            $user = $request->user();
            if (! $user) {
                return redirect()->route('login');
            }

            $path = $request->path();
            $redirectToIfDifferentPath = static function (string $routeName, array $params = []) use ($request) {
                if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                    return null;
                }
                $targetPath = trim((string) parse_url(route($routeName, $params), PHP_URL_PATH), '/');
                $currentPath = trim($request->path(), '/');
                if ($targetPath === $currentPath) {
                    return null;
                }

                return redirect()->route($routeName, $params);
            };
            if ($path === 'food' || str_starts_with($path, 'food/')) {
                if (method_exists($user, 'canManageFoodTongQuan') && $user->canManageFoodTongQuan()) {
                    return $redirectToIfDifferentPath('food');
                }
                if (method_exists($user, 'canManageFoodDoanhSo') && $user->canManageFoodDoanhSo()) {
                    return $redirectToIfDifferentPath('food', ['tab' => 'doanh-so']);
                }
                if (method_exists($user, 'canManageFoodSanPham') && $user->canManageFoodSanPham() && \Illuminate\Support\Facades\Route::has('food.san-pham')) {
                    return $redirectToIfDifferentPath('food.san-pham');
                }
                if (method_exists($user, 'canManageFoodBaoCao') && $user->canManageFoodBaoCao() && \Illuminate\Support\Facades\Route::has('food.bao-cao-ban-hang')) {
                    return $redirectToIfDifferentPath('food.bao-cao-ban-hang');
                }
                if (method_exists($user, 'canCreateFoodBuffOrder') && $user->canCreateFoodBuffOrder() && \Illuminate\Support\Facades\Route::has('food.dat-don')) {
                    return $redirectToIfDifferentPath('food.dat-don');
                }
                if (method_exists($user, 'canManageFoodThongKeBuff') && $user->canManageFoodThongKeBuff() && \Illuminate\Support\Facades\Route::has('food.thong-ke-buff')) {
                    return $redirectToIfDifferentPath('food.thong-ke-buff');
                }
                if (method_exists($user, 'canManageFoodReviews') && $user->canManageFoodReviews() && \Illuminate\Support\Facades\Route::has('food.reviews.index')) {
                    return $redirectToIfDifferentPath('food.reviews.index');
                }
                if (method_exists($user, 'canManageFoodEmployees') && $user->canManageFoodEmployees() && \Illuminate\Support\Facades\Route::has('food.nhan-vien')) {
                    return $redirectToIfDifferentPath('food.nhan-vien');
                }
                if (method_exists($user, 'canManageFoodChamCong') && $user->canManageFoodChamCong() && \Illuminate\Support\Facades\Route::has('food.cham-cong')) {
                    return $redirectToIfDifferentPath('food.cham-cong');
                }
                if (method_exists($user, 'canManageFoodXinNghi') && $user->canManageFoodXinNghi() && \Illuminate\Support\Facades\Route::has('food.xin-nghi')) {
                    return $redirectToIfDifferentPath('food.xin-nghi');
                }
                if (method_exists($user, 'canManageFoodUngLuong') && $user->canManageFoodUngLuong() && \Illuminate\Support\Facades\Route::has('food.ung-luong')) {
                    return $redirectToIfDifferentPath('food.ung-luong');
                }
                if (method_exists($user, 'canManageFoodLuong') && $user->canManageFoodLuong() && \Illuminate\Support\Facades\Route::has('food.luong')) {
                    return $redirectToIfDifferentPath('food.luong');
                }
                if (method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee() && \Illuminate\Support\Facades\Route::has('food.cham-cong')) {
                    return $redirectToIfDifferentPath('food.cham-cong');
                }
                if (method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong() && \Illuminate\Support\Facades\Route::has('food.qr-cham-cong')) {
                    return $redirectToIfDifferentPath('food.qr-cham-cong');
                }
                if (\Illuminate\Support\Facades\Route::has('food.cong-no')) {
                    return $redirectToIfDifferentPath('food.cong-no');
                }
            }

            return null;
        });
    })->create();
