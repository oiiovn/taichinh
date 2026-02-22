<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\Pay2sBankAccount;
use App\Models\TransactionHistory;
use App\Models\UserBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $plans = \App\Models\PlanConfig::getList();
        $currentPlan = $user->plan;
        $planExpiresAt = $user->plan_expires_at;
        $maxAccounts = $currentPlan && isset($plans[$currentPlan])
            ? (int) $plans[$currentPlan]['max_accounts']
            : 0;

        if (! $planExpiresAt || ! $planExpiresAt->isFuture()) {
            return redirect()->route('tai-chinh')->with('error', 'Gói của bạn đã hết hạn. Vui lòng gia hạn để thêm tài khoản ngân hàng.');
        }
        if ($maxAccounts < 1) {
            return redirect()->route('tai-chinh')->with('error', 'Gói hiện tại không cho phép liên kết tài khoản ngân hàng.');
        }

        $currentCount = $user->userBankAccounts()->count();
        if ($currentCount >= $maxAccounts) {
            return redirect()->route('tai-chinh')->with('error', 'Bạn đã đạt tối đa ' . $maxAccounts . ' tài khoản ngân hàng theo gói. Vui lòng nâng cấp gói.');
        }

        $bankCode = $request->input('bank_code') ?? '';
        $accountType = $request->input('account_type') ?? 'ca_nhan';
        $apiType = $request->input('api_type') ?? 'openapi';
        if ($bankCode === '' || ! in_array($bankCode, ['BIDV', 'ACB', 'MB', 'Vietcombank', 'VietinBank'], true)) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng chọn ngân hàng và thử lại.');
        }

        $input = $this->effectiveFormInput($request);
        $request->merge($input);

        $rules = $this->validationRulesForBank($bankCode, $accountType, $apiType);
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->route('tai-chinh')->withErrors($validator)->withInput()->with('error', 'Vui lòng nhập đầy đủ thông tin bắt buộc.');
        }

        $data = array_merge($validator->validated(), [
            'user_id' => $user->id,
            'bank_code' => $bankCode,
            'account_type' => $accountType,
            'api_type' => $apiType,
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'id_number' => $request->input('id_number'),
            'account_number' => $request->input('account_number'),
            'virtual_account_prefix' => $request->input('virtual_account_prefix'),
            'virtual_account_suffix' => $request->input('virtual_account_suffix'),
            'company_name' => $request->input('company_name'),
            'login_username' => $request->input('login_username'),
            'tax_code' => $request->input('tax_code'),
            'transaction_type' => $request->input('transaction_type'),
            'company_code' => $request->input('company_code'),
            'agreed_terms' => (bool) $request->input('agreed_terms'),
        ]);
        if (! empty($request->input('login_password'))) {
            $data['login_password'] = Crypt::encryptString($request->input('login_password'));
        }
        if ($bankCode === 'BIDV' && $apiType === 'openapi') {
            $data['virtual_account_prefix'] = $data['virtual_account_prefix'] ?? '963869';
        }

        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        if ($accountNumber !== '') {
            $alreadyByOther = UserBankAccount::where('account_number', $accountNumber)->where('user_id', '!=', $user->id)->exists();
            if ($alreadyByOther) {
                return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Số tài khoản này đã được liên kết bởi tài khoản khác. Mỗi thẻ chỉ có thể liên kết với một tài khoản.')->withInput();
            }
            $alreadyByMe = $user->userBankAccounts()->where('account_number', $accountNumber)->exists();
            if ($alreadyByMe) {
                return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Bạn đã liên kết tài khoản này rồi.')->withInput();
            }
        }

        try {
            UserBankAccount::create($data);
            return redirect()->route('tai-chinh')->with('success', 'Đã lưu tài khoản ngân hàng.');
        } catch (\Throwable $e) {
            Log::error('BankAccountController@store: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Không lưu được tài khoản ngân hàng. Vui lòng thử lại sau.')->withInput();
        }
    }

    public function updateAccountBalance(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }

        $request->validate([
            'account_number' => 'required|string',
            'balance' => 'required|numeric',
        ]);

        $accountNumber = trim((string) $request->input('account_number'));
        $linked = $user->userBankAccounts()->where('account_number', $accountNumber)->exists();
        if (! $linked) {
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Tài khoản không thuộc tài khoản liên kết của bạn.');
        }

        $balance = (float) $request->input('balance');
        $pay2s = Pay2sBankAccount::where('account_number', $accountNumber)->first();

        try {
            if ($pay2s) {
                $pay2s->update([
                    'balance' => $balance,
                    'last_synced_at' => now(),
                ]);
            } else {
                $uba = $user->userBankAccounts()->where('account_number', $accountNumber)->first();
                Pay2sBankAccount::create([
                    'external_id' => $accountNumber,
                    'account_number' => $accountNumber,
                    'account_holder_name' => $uba->full_name ?? $uba->company_name ?? null,
                    'bank_code' => $uba->bank_code ?? null,
                    'bank_name' => $uba->bank_name ?? null,
                    'balance' => $balance,
                    'last_synced_at' => now(),
                ]);
            }
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('success', 'Đã cập nhật số dư cuối.');
        } catch (\Throwable $e) {
            Log::error('BankAccountController@updateAccountBalance: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Không cập nhật được số dư. Vui lòng thử lại sau.');
        }
    }

    public function unlink(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Vui lòng đăng nhập.');
        }
        $accountNumber = trim((string) $request->input('account_number', ''));
        if ($accountNumber === '') {
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Thiếu thông tin tài khoản.');
        }
        $acc = $user->userBankAccounts()->where('account_number', $accountNumber)->first();
        if (! $acc) {
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Tài khoản không thuộc danh sách liên kết của bạn.');
        }
        try {
            $last4 = substr($accountNumber, -4);
            $acc->delete();
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('success', 'Đã gỡ liên kết tài khoản •••• ' . $last4 . '.');
        } catch (\Throwable $e) {
            Log::error('BankAccountController@unlink: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('tai-chinh', ['tab' => 'tai-khoan'])->with('error', 'Không gỡ được liên kết. Vui lòng thử lại sau.');
        }
    }

    private function effectiveFormInput(Request $request): array
    {
        $contentType = $request->header('Content-Type', '');
        if (strpos($contentType, 'application/x-www-form-urlencoded') === false) {
            return $request->all();
        }
        $content = $request->getContent();
        if ($content === '' || $content === null) {
            return $request->all();
        }
        $multi = [];
        foreach (explode('&', $content) as $pair) {
            $eq = strpos($pair, '=');
            if ($eq !== false) {
                $k = urldecode(substr($pair, 0, $eq));
                $v = urldecode(substr($pair, $eq + 1));
                if (! isset($multi[$k])) {
                    $multi[$k] = [];
                }
                $multi[$k][] = $v;
            }
        }
        $out = [];
        foreach ($multi as $key => $values) {
            $lastNonEmpty = null;
            foreach (array_reverse($values) as $v) {
                if ((string) $v !== '') {
                    $lastNonEmpty = $v;
                    break;
                }
            }
            $out[$key] = $lastNonEmpty ?? ($values[0] ?? '');
        }
        return array_merge($request->all(), $out);
    }

    private function validationRulesForBank(string $bankCode, string $accountType = 'ca_nhan', string $apiType = 'openapi'): array
    {
        $base = [
            'bank_code' => 'required|string|in:BIDV,ACB,MB,Vietcombank,VietinBank',
            'account_type' => 'nullable|string|in:ca_nhan,doanh_nghiep',
            'api_type' => 'nullable|string|in:openapi,pay2s',
            'agreed_terms' => 'required|accepted',
        ];

        $key = $bankCode . '_' . $accountType . '_' . $apiType;
        $byCombo = [
            'BIDV_ca_nhan_openapi' => ['full_name', 'email', 'phone', 'id_number', 'account_number'],
            'BIDV_ca_nhan_pay2s' => ['login_username', 'login_password', 'account_number'],
            'ACB_ca_nhan_openapi' => ['full_name', 'phone', 'account_number'],
            'ACB_ca_nhan_pay2s' => ['full_name', 'phone', 'account_number'],
            'ACB_doanh_nghiep_openapi' => ['company_name', 'phone', 'account_number', 'login_username'],
            'ACB_doanh_nghiep_pay2s' => ['company_name', 'phone', 'account_number', 'login_username'],
            'MB_ca_nhan_openapi' => ['full_name', 'email', 'phone', 'id_number', 'account_number'],
            'MB_ca_nhan_pay2s' => ['login_username', 'login_password', 'account_number'],
            'MB_doanh_nghiep_openapi' => ['company_name', 'phone', 'tax_code', 'account_number'],
            'MB_doanh_nghiep_pay2s' => ['login_username', 'login_password', 'account_number', 'company_code'],
            'Vietcombank_ca_nhan_pay2s' => ['login_username', 'login_password', 'account_number'],
            'Vietcombank_doanh_nghiep_pay2s' => ['login_username', 'login_password', 'account_number'],
            'VietinBank_ca_nhan_pay2s' => ['login_username', 'login_password', 'account_number'],
            'VietinBank_doanh_nghiep_pay2s' => ['login_username', 'login_password', 'account_number'],
        ];

        foreach ($byCombo as $combo => $fields) {
            if ($combo === $key) {
                foreach ($fields as $f) {
                    $base[$f] = 'required|string|max:255';
                    if ($f === 'login_password') {
                        $base[$f] = 'required|string|max:500';
                    }
                    if (in_array($f, ['email'], true)) {
                        $base[$f] = 'required|email';
                    }
                }
                break;
            }
        }

        if (! isset($byCombo[$key])) {
            $base['account_number'] = 'required|string|max:50';
        }

        return $base;
    }
}
