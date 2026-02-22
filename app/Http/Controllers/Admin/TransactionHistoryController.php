<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Services\Pay2sApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = TransactionHistory::orderBy('transaction_date', 'desc');

        if ($request->filled('type') && in_array($request->type, ['IN', 'OUT'])) {
            $query->where('type', $request->type);
        }
        if ($request->filled('tu_ngay')) {
            $query->whereDate('transaction_date', '>=', $request->tu_ngay);
        }
        if ($request->filled('den_ngay')) {
            $query->whereDate('transaction_date', '<=', $request->den_ngay);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('description', 'like', "%{$kw}%")
                    ->orWhere('account_number', 'like', "%{$kw}%")
                    ->orWhere('external_id', 'like', "%{$kw}%");
            });
        }

        $transactions = $query->paginate(50)->withQueryString();
        $pay2sService = new Pay2sApiService;

        return view('pages.admin.lich-su-giao-dich', [
            'title' => 'Lịch sử giao dịch',
            'transactionHistory' => $transactions,
            'pay2sConfigured' => $pay2sService->hasConfig(),
        ]);
    }

    public function sync(): RedirectResponse
    {
        $service = new Pay2sApiService;
        $result = $service->sync();
        if (! empty($result['errors'])) {
            return back()->with('error', implode(' ', $result['errors']));
        }
        $msg = sprintf('Đồng bộ xong: %d tài khoản, %d giao dịch mới.', $result['accounts'], $result['transactions']);
        if ($result['transactions'] === 0 && $result['accounts'] === 0) {
            $msg .= ' Không lấy được dữ liệu: kiểm tra Hệ thống (Secret Key, Base URL https://my.pay2s.vn, Từ ngày–Đến ngày). Xem storage/logs/laravel.log nếu cần.';
        }
        return back()->with('success', $msg);
    }
}
