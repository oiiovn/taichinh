<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateGlobalMerchantPatternJob;
use App\Models\GlobalMerchantPattern;
use App\Models\TransactionHistory;
use App\Models\UserCategory;
use App\Models\UserMerchantRule;
use App\Services\UserFinancialContextService;
use App\Models\UserBehaviorPattern;
use App\Services\MerchantKeyNormalizer;
use App\Services\TransactionClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GiaoDichController extends Controller
{
    public function giaoDichTable(Request $request)
    {
        try {
            $user = $request->user();
            $contextSvc = app(UserFinancialContextService::class);
            $context = $user ? $contextSvc->ensureCategoriesAndGetContext($user) : ['userBankAccounts' => collect(), 'linkedAccountNumbers' => [], 'accounts' => collect(), 'accountBalances' => []];
            $linkedAccountNumbers = $context['linkedAccountNumbers'];

            $transactions = $user && ! empty($linkedAccountNumbers)
                ? $contextSvc->getPaginatedTransactions($user, $linkedAccountNumbers, $request, 50)
                : TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString();

            $userCategories = $user ? $user->userCategories()->withCount('transactionHistories')->orderByDesc('transaction_histories_count')->orderBy('type')->orderBy('name')->get() : collect();

            return response()->view('pages.tai-chinh.partials.giao-dich-table', [
                'transactionHistory' => $transactions,
                'userCategories' => $userCategories,
                'linkedAccountNumbers' => $linkedAccountNumbers,
            ]);
        } catch (\Throwable $e) {
            Log::error('GiaoDichController@giaoDichTable: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $emptyPaginator = TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString();
            return response()->view('pages.tai-chinh.partials.giao-dich-table', [
                'transactionHistory' => $emptyPaginator,
                'userCategories' => collect(),
                'linkedAccountNumbers' => [],
                'load_error' => true,
                'load_error_message' => 'Không tải được danh sách giao dịch. Vui lòng thử lại sau.',
            ]);
        }
    }

    /**
     * API: Trả danh sách giao dịch dạng JSON (cho app mobile).
     * Query: page, per_page (mặc định 20), stk, loai, q, category_id (giống web).
     */
    public function giaoDichJson(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $contextSvc = app(UserFinancialContextService::class);
            $context = $contextSvc->ensureCategoriesAndGetContext($user);
            $linkedAccountNumbers = $context['linkedAccountNumbers'];
            $perPage = min((int) $request->input('per_page', 20), 100);
            $transactions = ! empty($linkedAccountNumbers)
                ? $contextSvc->getPaginatedTransactions($user, $linkedAccountNumbers, $request, $perPage)
                : TransactionHistory::where('user_id', $user->id)->whereRaw('1 = 0')->paginate($perPage)->withQueryString();

            return response()->json([
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('GiaoDichController@giaoDichJson: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Không tải được danh sách giao dịch.'], 500);
        }
    }

    public function confirmClassification(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $wantsJson = $request->wantsJson();
        $redirectToTab = function ($query = []) {
            $params = array_merge(['tab' => 'giao-dich'], $query);
            return redirect()->route('tai-chinh', $params);
        };
        $jsonErr = function (string $message, int $code = 422) {
            return response()->json(['success' => false, 'message' => $message], $code);
        };

        if (! $user) {
            return $wantsJson ? $jsonErr('Vui lòng đăng nhập.', 401) : $redirectToTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transaction_history,id',
            'user_category_id' => 'required|integer|exists:user_categories,id',
        ], [
            'transaction_ids.required' => 'Vui lòng tích chọn ít nhất một giao dịch.',
            'transaction_ids.min' => 'Vui lòng tích chọn ít nhất một giao dịch.',
            'user_category_id.required' => 'Vui lòng chọn danh mục.',
            'user_category_id.exists' => 'Danh mục không hợp lệ.',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }
            return $redirectToTab(request()->has('page') ? ['page' => request('page')] : [])->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $transactionIds = array_unique(array_map('intval', $validated['transaction_ids']));
        $categoryId = (int) $validated['user_category_id'];

        $userCategory = UserCategory::where('id', $categoryId)->where('user_id', $user->id)->first();
        if (! $userCategory) {
            return $wantsJson ? $jsonErr('Danh mục không hợp lệ.', 400) : $redirectToTab(request()->has('page') ? ['page' => request('page')] : [])->with('error', 'Danh mục không hợp lệ.');
        }

        $transactions = TransactionHistory::whereIn('id', $transactionIds)
            ->where('user_id', $user->id)
            ->get();

        if ($transactions->isEmpty()) {
            return $wantsJson ? $jsonErr('Không có giao dịch hợp lệ để cập nhật.', 400) : $redirectToTab(request()->has('page') ? ['page' => request('page')] : [])->with('error', 'Không có giao dịch hợp lệ để cập nhật.');
        }

        try {
            $updatePayload = [
                'user_category_id' => $categoryId,
                'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_USER_CONFIRMED,
                'classification_confidence' => 1.0,
            ];
            /** @var MerchantKeyNormalizer $normalizer */
            $normalizer = app(MerchantKeyNormalizer::class);
            $merchantKeysDone = [];
            $merchantGroupsDone = [];

            $req = request();
            DB::transaction(function () use ($transactions, $categoryId, $user, $normalizer, $req, &$merchantKeysDone, &$merchantGroupsDone) {
                foreach ($transactions as $transaction) {
                    $pair = $transaction->merchant_key
                        ? ['merchant_key' => $transaction->merchant_key, 'merchant_group' => $transaction->merchant_group ?: $transaction->merchant_key]
                        : $normalizer->normalizeWithGroup($transaction->description);
                    $merchantKey = $pair['merchant_key'];
                    $merchantGroup = $pair['merchant_group'] ?? $merchantKey;

                    $oldSource = $transaction->classification_source;
                    $oldSystemCategoryId = $transaction->system_category_id;

                    if ($oldSource === TransactionClassifier::SOURCE_GLOBAL && $oldSystemCategoryId && $transaction->merchant_group) {
                        $bucket = $transaction->amount_bucket ?? '';
                        $pattern = GlobalMerchantPattern::where('merchant_group', $transaction->merchant_group)
                            ->where('direction', $transaction->type)
                            ->where('amount_bucket', $bucket)
                            ->where('system_category_id', $oldSystemCategoryId)->first();
                        if (! $pattern && ($bucket !== '' || $transaction->type !== '')) {
                            $pattern = GlobalMerchantPattern::where('merchant_group', $transaction->merchant_group)
                                ->where('direction', '')->where('amount_bucket', '')
                                ->where('system_category_id', $oldSystemCategoryId)->first();
                        }
                        if ($pattern) {
                            $pattern->update(['confidence_score' => max(0, $pattern->confidence_score - 0.1)]);
                        }
                    }
                    if ($oldSource === TransactionClassifier::SOURCE_AI && $oldSystemCategoryId && $transaction->merchant_group && $transaction->amount_bucket) {
                        $pattern = GlobalMerchantPattern::where('merchant_group', $transaction->merchant_group)
                            ->where('direction', $transaction->type)
                            ->where('amount_bucket', $transaction->amount_bucket)
                            ->where('system_category_id', $oldSystemCategoryId)->first();
                        if ($pattern) {
                            $pattern->update(['confidence_score' => max(0, $pattern->confidence_score - 0.1)]);
                        }
                    }
                    if ($oldSource === TransactionClassifier::SOURCE_BEHAVIOR && $transaction->merchant_group && $transaction->amount_bucket) {
                        $beh = UserBehaviorPattern::where('user_id', $user->id)
                            ->where('merchant_group', $transaction->merchant_group)
                            ->where('direction', $transaction->type)
                            ->where('amount_bucket', $transaction->amount_bucket)
                            ->where('user_category_id', $transaction->user_category_id)->first();
                        if ($beh) {
                            $beh->update(['confidence_score' => max(0, $beh->confidence_score - 0.05)]);
                        }
                    }
                    if ($oldSource === TransactionClassifier::SOURCE_RECURRING && $transaction->merchant_group) {
                        $rec = \App\Models\UserRecurringPattern::where('user_id', $user->id)
                            ->where('merchant_group', $transaction->merchant_group)
                            ->where('direction', $transaction->type)->first();
                        if ($rec) {
                            $newScore = max(0, $rec->confidence_score - 0.1);
                            $rec->update([
                                'confidence_score' => $newScore,
                                'status' => $newScore <= 0.3 ? \App\Models\UserRecurringPattern::STATUS_WEAK : $rec->status,
                            ]);
                        }
                    }

                    $rule = UserMerchantRule::firstOrCreate(
                        ['user_id' => $user->id, 'merchant_key' => $merchantKey],
                        ['mapped_user_category_id' => $categoryId, 'confirmed_count' => 0, 'confidence_score' => 0]
                    );

                    if ($rule->wasRecentlyCreated) {
                        $rule->update([
                            'confirmed_count' => 1,
                            'confidence_score' => min(1.0, 0.15),
                            'last_confirmed_at' => now(),
                        ]);
                    } else {
                        $rule->increment('confirmed_count');
                        $rule = $rule->fresh();
                        $rule->update([
                            'confidence_score' => min(1.0, $rule->confirmed_count * 0.15),
                            'last_confirmed_at' => now(),
                            'mapped_user_category_id' => $categoryId,
                        ]);
                    }

                    if (! $transaction->amount_bucket) {
                        $transaction->amount_bucket = TransactionHistory::resolveAmountBucket((int) round((float) $transaction->amount));
                        $transaction->save();
                    }

                    $descFromRequest = $req->input('transaction_descriptions.' . $transaction->id);
                    $transaction->description = $descFromRequest !== null ? (string) $descFromRequest : ($transaction->description ?? '');
                    $transaction->merchant_key = $merchantKey;
                    $transaction->merchant_group = $merchantGroup;
                    $transaction->user_category_id = $categoryId;
                    $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_USER_CONFIRMED;
                    $transaction->classification_confidence = 1.0;
                    $transaction->save();

                    $behavior = UserBehaviorPattern::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'merchant_group' => $merchantGroup,
                            'direction' => $transaction->type,
                            'amount_bucket' => $transaction->amount_bucket,
                        ],
                        ['user_category_id' => $categoryId, 'usage_count' => 0, 'confidence_score' => 0.1]
                    );
                    $behavior->increment('usage_count');
                    $behavior->refresh();
                    $behavior->update([
                        'confidence_score' => min(1.0, $behavior->confidence_score + 0.1),
                        'user_category_id' => $categoryId,
                    ]);

                    if (! in_array($merchantKey, $merchantKeysDone, true)) {
                        $merchantKeysDone[] = $merchantKey;
                    }
                    if (! in_array($merchantGroup, $merchantGroupsDone, true)) {
                        $merchantGroupsDone[] = $merchantGroup;
                    }
                }
            });

            $systemCategoryId = $userCategory->based_on_system_category_id;
            if ($systemCategoryId !== null) {
                foreach ($merchantGroupsDone as $mg) {
                    UpdateGlobalMerchantPatternJob::dispatch($mg, (int) $systemCategoryId);
                }
            }

            $query = request()->has('page') ? ['page' => request('page')] : [];
            $count = $transactions->count();
            $successMessage = "Đã lưu danh mục cho {$count} giao dịch đã chọn. Các giao dịch sau có nội dung na ná sẽ được ưu tiên phân loại theo rule.";
            if ($wantsJson) {
                return response()->json(['success' => true, 'message' => $successMessage, 'count' => $count]);
            }
            return $redirectToTab($query)->with('success', $successMessage);
        } catch (\Throwable $e) {
            Log::error('GiaoDichController@confirmClassification: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $errMessage = 'Không lưu được danh mục. Vui lòng thử lại sau.';
            return $wantsJson ? $jsonErr($errMessage, 500) : $redirectToTab(request()->has('page') ? ['page' => request('page')] : [])->with('error', $errMessage);
        }
    }

}
