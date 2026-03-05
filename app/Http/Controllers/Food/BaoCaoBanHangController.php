<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodProduct;
use App\Models\FoodReportBonusTier;
use App\Models\FoodReportDebt;
use App\Models\FoodSalesReport;
use App\Models\FoodSalesReportItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Helpers\VndHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BaoCaoBanHangController extends Controller
{
    private const HEADERS = [
        'Nhóm hàng', 'Mã hàng', 'Tên hàng', 'Đơn vị tính', 'SL Bán', 'Giá trị niêm yết', 'Doanh thu', 'Chênh lệch',
        'SL Trả', 'Giá trị trả', 'Doanh thu thuần', 'Mã hóa đơn', 'Thời gian', 'Người nhận đơn', 'Khách hàng', 'SL',
        'Giá trị niêm yết chi tiết', 'Doanh thu chi tiết', 'Giá trị bán chi tiết',
    ];

    private const HEADER_TO_KEY = [
        'Nhóm hàng' => 'nhom_hang',
        'Mã hàng' => 'ma_hang',
        'Tên hàng' => 'ten_hang',
        'Đơn vị tính' => 'don_vi_tinh',
        'SL Bán' => 'sl_ban',
        'Giá trị niêm yết' => 'gia_tri_niem_yet',
        'Doanh thu' => 'doanh_thu',
        'Chênh lệch' => 'chenh_lech',
        'SL Trả' => 'sl_tra',
        'Giá trị trả' => 'gia_tri_tra',
        'Doanh thu thuần' => 'doanh_thu_thuan',
        'Mã hóa đơn' => 'ma_hoa_don',
        'Thời gian' => 'thoi_gian',
        'Người nhận đơn' => 'nguoi_nhan_don',
        'Khách hàng' => 'khach_hang',
        'SL' => 'sl',
        'Giá trị niêm yết chi tiết' => 'gia_tri_niem_yet_chi_tiet',
        'Doanh thu chi tiết' => 'doanh_thu_chi_tiet',
        'Giá trị bán chi tiết' => 'gia_tri_ban_chi_tiet',
    ];

    private const TIEN_CONG_THAP = 10000;

    private const TIEN_CONG_CAO = 20000;

    private const NGUONG_VON_TIEN_CONG_CAO = 60000;

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $reports = FoodSalesReport::query()
            ->with(['debts.debtor', 'debts.payment'])
            ->where('user_id', $user->id)
            ->orderByDesc('report_date')
            ->orderByDesc('uploaded_at')
            ->get();

        $users = \App\Models\User::query()->orderBy('name')->get()->filter(fn ($u) => $u->canUseFeature('food'))->values()->map(fn ($u) => (object) ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return view('pages.food.bao-cao-ban-hang', [
            'title' => 'Báo cáo bán hàng',
            'reports' => $reports,
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $v = Validator::make($request->all(), [
            'data' => 'required|string',
        ]);
        if ($v->fails()) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Thiếu dữ liệu dán.');
        }

        $raw = trim((string) $request->input('data'));
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (count($lines) < 2) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Cần ít nhất 1 dòng header và 1 dòng dữ liệu.');
        }

        $headerLine = array_shift($lines);
        $headerCells = $this->parseRow($headerLine);
        $productPrices = FoodProduct::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('ma_hang');

        $rows = [];
        $reportDate = null;
        $maHoaDonSet = [];

        foreach ($lines as $line) {
            $cells = $this->parseRow($line);
            if (count($cells) === 0) {
                continue;
            }
            $row = [];
            foreach ($headerCells as $i => $headerName) {
                $key = self::HEADER_TO_KEY[trim($headerName)] ?? null;
                if ($key !== null && isset($cells[$i])) {
                    $val = trim($cells[$i]);
                    if (in_array($key, ['sl_ban', 'sl', 'sl_tra'], true)) {
                        $val = (float) str_replace([',', ' '], ['.', ''], $val);
                    }
                    if (in_array($key, ['gia_tri_niem_yet', 'doanh_thu', 'chenh_lech', 'gia_tri_tra', 'doanh_thu_thuan', 'gia_tri_niem_yet_chi_tiet', 'doanh_thu_chi_tiet', 'gia_tri_ban_chi_tiet'], true)) {
                        $val = VndHelper::parseAmount($val);
                    }
                    $row[$key] = $val;
                }
            }
            if (empty($row['ma_hoa_don'])) {
                continue;
            }
            $rows[] = $row;
            $maHoaDonSet[$row['ma_hoa_don']] = true;
            if (! empty($row['thoi_gian'])) {
                $dt = $this->parseThoiGian($row['thoi_gian']);
                if ($dt && ($reportDate === null || $dt->gt($reportDate))) {
                    $reportDate = $dt;
                }
            }
        }

        if (count($rows) === 0) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không có dòng dữ liệu hợp lệ (cần Mã hóa đơn).');
        }

        $reportDate = $reportDate ? $reportDate->toDateString() : now()->toDateString();
        $totalOrders = count($maHoaDonSet);

        $orderCosts = [];
        foreach ($rows as $row) {
            $maHang = $row['ma_hang'] ?? '';
            $sl = (float) ($row['sl'] ?? $row['sl_ban'] ?? 0);
            $giaVon = $productPrices->get($maHang)?->gia_von ?? 0;
            $lineCost = (float) $giaVon * $sl;
            $don = $row['ma_hoa_don'];
            $orderCosts[$don] = ($orderCosts[$don] ?? 0) + $lineCost;
        }

        $totalCost = array_sum($orderCosts);
        $totalTienCong = 0;
        foreach ($orderCosts as $donCost) {
            $totalTienCong += $donCost > self::NGUONG_VON_TIEN_CONG_CAO ? self::TIEN_CONG_CAO : self::TIEN_CONG_THAP;
        }

        $bonus = FoodReportBonusTier::getBonusForTotalCost($totalCost);

        $nextCode = $this->nextReportCode($user->id);

        $report = FoodSalesReport::query()->create([
            'user_id' => $user->id,
            'report_code' => $nextCode,
            'report_date' => $reportDate,
            'total_orders' => $totalOrders,
            'total_cost' => $totalCost,
            'total_tien_cong' => $totalTienCong,
            'bonus' => (int) round($bonus),
            'uploaded_at' => now(),
        ]);

        foreach ($rows as $row) {
            $maHang = $row['ma_hang'] ?? '';
            $row['gia_von_unit'] = VndHelper::toStoredAmount($productPrices->get($maHang)?->gia_von ?? 0);
            $report->items()->create($row);
        }

        return redirect()->route('food.bao-cao-ban-hang.show', $report)->with('success', 'Đã tạo báo cáo '.$nextCode);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $report = FoodSalesReport::query()->with('items')->find($id);
        if (! $report) {
            abort(404);
        }

        $isOwner = (int) $report->user_id === (int) $user->id;
        $isAdmin = $user->is_admin;
        $isDebtor = FoodReportDebt::query()
            ->where('food_sales_report_id', $report->id)
            ->where('debtor_user_id', $user->id)
            ->exists();

        if (! $isOwner && ! $isAdmin && ! $isDebtor) {
            abort(403, 'Bạn không có quyền xem báo cáo này.');
        }

        $productPrices = FoodProduct::query()
            ->where('user_id', $report->user_id)
            ->get()
            ->keyBy('ma_hang');

        $orders = [];
        $tienCongThap = self::TIEN_CONG_THAP;
        $tienCongCao = self::TIEN_CONG_CAO;
        $nguong = self::NGUONG_VON_TIEN_CONG_CAO;

        foreach ($report->items as $item) {
            $don = $item->ma_hoa_don ?? '_';
            if (! isset($orders[$don])) {
                $orders[$don] = [
                    'ma_hoa_don' => $item->ma_hoa_don,
                    'thoi_gian' => $item->thoi_gian,
                    'nguoi_nhan_don' => $item->nguoi_nhan_don,
                    'khach_hang' => $item->khach_hang,
                    'items' => [],
                    'total_cost' => 0,
                ];
            }
            $stored = (float) $item->gia_von_unit;
            $fromProduct = (float) ($productPrices->get($item->ma_hang)?->gia_von ?? 0);
            // Giá lưu thiếu hàng nghìn (vd. 21 thay vì 21,822): ưu tiên giá sản phẩm khi có và giá lưu quá nhỏ
            if ($fromProduct > 0 && ($stored <= 0 || $stored < 100 || ($stored < 1000 && $fromProduct >= 1000))) {
                $giaVon = $fromProduct;
            } else {
                $giaVon = $stored ?: $fromProduct;
            }
            $sl = (float) ($item->sl ?? $item->sl_ban ?? 0);
            $lineCost = (float) $giaVon * $sl;
            $orders[$don]['total_cost'] += $lineCost;
            $orders[$don]['items'][] = [
                'item' => $item,
                'gia_von_unit' => (float) $giaVon,
                'sl' => $sl,
                'line_cost' => $lineCost,
            ];
        }

        foreach ($orders as &$ord) {
            $ord['tien_cong'] = $ord['total_cost'] > $nguong ? $tienCongCao : $tienCongThap;
        }
        unset($ord);

        $ordersArray = array_values($orders);
        $recalculatedTotalCost = array_sum(array_column($ordersArray, 'total_cost'));
        $recalculatedTienCong = array_sum(array_column($ordersArray, 'tien_cong'));

        $users = ($isOwner || $isAdmin)
            ? User::query()->orderBy('name')->get()->filter(fn ($u) => $u->canUseFeature('food'))->values()->map(fn ($u) => (object) ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            : collect();
        $canManage = $isAdmin || $isOwner;

        return view('pages.food.bao-cao-ban-hang-show', [
            'title' => 'Chi tiết báo cáo '.$report->report_code,
            'report' => $report,
            'orders' => $ordersArray,
            'display_total_cost' => $recalculatedTotalCost,
            'display_total_tien_cong' => $recalculatedTienCong,
            'users' => $users,
            'canManage' => $canManage,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $report = FoodSalesReport::query()->where('user_id', $user->id)->find($id);
        if (! $report) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không tìm thấy báo cáo.');
        }

        $report->delete();

        return redirect()->route('food.bao-cao-ban-hang')->with('success', 'Đã xóa báo cáo '.$report->report_code);
    }

    public function storeCongNo(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $report = FoodSalesReport::query()->where('user_id', $user->id)->find($id);
        if (! $report) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không tìm thấy báo cáo.');
        }

        $debtorUserId = (int) $request->input('debtor_user_id');
        if (! $debtorUserId) {
            return redirect()->back()->with('error', 'Vui lòng chọn user.');
        }

        $debtor = \App\Models\User::find($debtorUserId);
        if (! $debtor) {
            return redirect()->back()->with('error', 'User không tồn tại.');
        }

        FoodReportDebt::query()->updateOrCreate(
            [
                'food_sales_report_id' => $report->id,
                'debtor_user_id' => $debtorUserId,
            ],
            ['only_tien_cong' => $request->boolean('only_tien_cong')]
        );

        return redirect()->back()->with('success', 'Đã tạo công nợ cho '.$debtor->name);
    }

    private function parseRow(string $line): array
    {
        return array_map('trim', explode("\t", $line));
    }

    private function parseThoiGian(string $s): ?Carbon
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $s);
        } catch (\Throwable $e) {
            try {
                return Carbon::parse($s);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    private function nextReportCode(int $userId): string
    {
        $last = FoodSalesReport::query()
            ->where('user_id', $userId)
            ->where('report_code', 'like', 'BC%')
            ->orderByRaw('CAST(SUBSTRING(report_code, 3) AS UNSIGNED) DESC')
            ->value('report_code');

        if (! $last || ! preg_match('/^BC(\d+)$/', $last, $m)) {
            return 'BC00001';
        }

        $num = (int) $m[1] + 1;

        return 'BC'.str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }
}
