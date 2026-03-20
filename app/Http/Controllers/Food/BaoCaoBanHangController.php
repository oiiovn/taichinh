<?php

namespace App\Http\Controllers\Food;

use App\Helpers\VndHelper;
use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodBuffOrder;
use App\Models\FoodProduct;
use App\Models\FoodReportBonusTier;
use App\Models\FoodReportDebt;
use App\Models\FoodSalesReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            ->with(['debts.debtor', 'debts.payment', 'branch'])
            ->where('user_id', $user->id)
            ->orderByDesc('report_date')
            ->orderByDesc('uploaded_at')
            ->get();

        $users = \App\Models\User::query()->orderBy('name')->get()->filter(fn ($u) => $u->canUseFeature('food'))->values()->map(fn ($u) => (object) ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        $branches = FoodBranch::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        return view('pages.food.bao-cao-ban-hang', [
            'title' => 'Báo cáo bán hàng',
            'reports' => $reports,
            'users' => $users,
            'branches' => $branches,
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
            'food_branch_id' => [
                'nullable',
                'integer',
                Rule::exists('food_branches', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
            ],
        ]);
        if ($v->fails()) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Thiếu dữ liệu dán hoặc chi nhánh không hợp lệ.');
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
        $excludedInvoices = [];

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
            if ($this->isExcludedItemName($row['ten_hang'] ?? null)) {
                $excludedInvoices[(string) $row['ma_hoa_don']] = true;
            }
            $rows[] = $row;
        }

        $rowsBuff = [];
        if ($excludedInvoices !== []) {
            $rowsBuff = array_values(array_filter($rows, fn ($row) => isset($excludedInvoices[(string) ($row['ma_hoa_don'] ?? '')])));
            $rows = array_values(array_filter($rows, fn ($row) => ! isset($excludedInvoices[(string) ($row['ma_hoa_don'] ?? '')])));
        }

        $branchId = $request->input('food_branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        $buffCount = $this->saveBuffOrders($user->id, $branchId, $rowsBuff);

        if (count($rows) === 0) {
            if ($buffCount > 0) {
                return redirect()->route('food.bao-cao-ban-hang')->with('success', 'Đã ghi nhận '.$buffCount.' đơn Quán Ship Bù. Không có dữ liệu báo cáo thường để tạo báo cáo bán hàng.');
            }

            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không có dòng dữ liệu hợp lệ sau khi loại các đơn chứa tên hàng Quán Ship Bù.');
        }

        $reportDate = null;
        $maHoaDonSet = [];
        foreach ($rows as $row) {
            $maHoaDonSet[(string) $row['ma_hoa_don']] = true;
            if (! empty($row['thoi_gian'])) {
                $dt = $this->parseThoiGian($row['thoi_gian']);
                if ($dt && ($reportDate === null || $dt->gt($reportDate))) {
                    $reportDate = $dt;
                }
            }
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
            'food_branch_id' => $branchId,
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

        $report = FoodSalesReport::query()->with(['items', 'branch', 'debts.debtor', 'debts.payment'])->find($id);
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

        $branches = $canManage
            ? FoodBranch::query()->where('user_id', $report->user_id)->orderBy('name')->get()
            : collect();

        return view('pages.food.bao-cao-ban-hang-show', [
            'title' => 'Chi tiết báo cáo '.$report->report_code,
            'report' => $report,
            'orders' => $ordersArray,
            'display_total_cost' => $recalculatedTotalCost,
            'display_total_tien_cong' => $recalculatedTienCong,
            'users' => $users,
            'canManage' => $canManage,
            'branches' => $branches,
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

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $report = FoodSalesReport::query()->where('user_id', $user->id)->find($id);
        if (! $report) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không tìm thấy báo cáo.');
        }

        if ($request->exists('note')) {
            $report->note = $request->input('note');
        }

        if ($request->exists('food_branch_id')) {
            $v = Validator::make($request->only('food_branch_id'), [
                'food_branch_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('food_branches', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
                ],
            ]);
            if ($v->fails()) {
                return redirect()->back()->with('error', 'Chi nhánh không hợp lệ.');
            }
            $bid = $request->input('food_branch_id');
            $report->food_branch_id = ($bid === null || $bid === '') ? null : (int) $bid;
        }

        $report->save();

        $redirect = $request->input('from') === 'show'
            ? route('food.bao-cao-ban-hang.show', $report)
            : route('food.bao-cao-ban-hang');

        $saved = [];
        if ($request->exists('note')) {
            $saved[] = 'ghi chú';
        }
        if ($request->exists('food_branch_id')) {
            $saved[] = 'chi nhánh';
        }
        $msg = $saved !== [] ? 'Đã lưu '.implode(' và ', $saved).'.' : 'Đã lưu.';

        return redirect($redirect)->with('success', $msg);
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

        $onlyTienCong = $request->boolean('only_tien_cong');
        $bonus = (float) ($report->bonus ?? 0);
        $baseAmount = $onlyTienCong
            ? (float) $report->total_tien_cong + $bonus
            : (float) $report->total_cost + (float) $report->total_tien_cong + $bonus;

        $deductionAmount = (float) $request->input('deduction_amount', 0);
        if ($deductionAmount < 0 || $deductionAmount > $baseAmount) {
            return redirect()->back()->with('error', 'Số tiền trừ công nợ phải từ 0 đến '.number_format($baseAmount, 0, ',', '.').' đ.');
        }
        $deductionAmount = (int) round($deductionAmount);

        FoodReportDebt::query()->updateOrCreate(
            [
                'food_sales_report_id' => $report->id,
                'debtor_user_id' => $debtorUserId,
            ],
            [
                'only_tien_cong' => $onlyTienCong,
                'deduction_amount' => $deductionAmount,
            ]
        );

        return redirect()->back()->with('success', 'Đã tạo công nợ cho '.$debtor->name);
    }

    public function updateDoanhSo(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $report = FoodSalesReport::query()->where('user_id', $user->id)->find($id);
        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy báo cáo.'], 404);
        }

        $value = $request->input('doanh_so');
        if ($value !== null && trim((string) $value) !== '') {
            $report->doanh_so = VndHelper::toStoredAmount($value);
        } else {
            $report->doanh_so = null;
        }
        $report->save();

        return response()->json([
            'success' => true,
            'doanh_so' => $report->doanh_so,
            'loi_nhuan' => $report->loi_nhuan,
        ]);
    }

    private function saveBuffOrders(int $userId, ?int $branchId, array $rowsBuff): int
    {
        if ($rowsBuff === []) {
            return 0;
        }

        $byInvoice = [];
        foreach ($rowsBuff as $row) {
            $invoice = trim((string) ($row['ma_hoa_don'] ?? ''));
            if ($invoice === '') {
                continue;
            }
            if (! isset($byInvoice[$invoice])) {
                $byInvoice[$invoice] = [
                    'invoice_code' => $invoice,
                    'order_time_text' => (string) ($row['thoi_gian'] ?? ''),
                    'receiver_name' => (string) ($row['nguoi_nhan_don'] ?? ''),
                    'customer_name' => (string) ($row['khach_hang'] ?? ''),
                    'order_date' => null,
                ];
            }
            $d = null;
            if (! empty($row['thoi_gian'])) {
                $d = $this->parseThoiGian((string) $row['thoi_gian']);
            }
            if ($d && (! $byInvoice[$invoice]['order_date'] || $d->gt($byInvoice[$invoice]['order_date']))) {
                $byInvoice[$invoice]['order_date'] = $d;
                $byInvoice[$invoice]['order_time_text'] = (string) ($row['thoi_gian'] ?? '');
            }
            if ($byInvoice[$invoice]['receiver_name'] === '' && ! empty($row['nguoi_nhan_don'])) {
                $byInvoice[$invoice]['receiver_name'] = (string) $row['nguoi_nhan_don'];
            }
            if ($byInvoice[$invoice]['customer_name'] === '' && ! empty($row['khach_hang'])) {
                $byInvoice[$invoice]['customer_name'] = (string) $row['khach_hang'];
            }
        }

        $count = 0;
        foreach ($byInvoice as $invoice => $it) {
            $date = $it['order_date'] ? $it['order_date']->toDateString() : now()->toDateString();
            FoodBuffOrder::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'invoice_code' => $invoice,
                    'order_date' => $date,
                ],
                [
                    'food_branch_id' => $branchId,
                    'order_time_text' => $it['order_time_text'] !== '' ? $it['order_time_text'] : null,
                    'receiver_name' => $it['receiver_name'] !== '' ? $it['receiver_name'] : null,
                    'customer_name' => $it['customer_name'] !== '' ? $it['customer_name'] : null,
                    'buff_amount' => 20000,
                    'labor_amount' => 10000,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function parseRow(string $line): array
    {
        return array_map('trim', explode("\t", $line));
    }

    private function isExcludedItemName(?string $tenHang): bool
    {
        if (! is_string($tenHang)) {
            return false;
        }

        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $tenHang)));

        return $normalized === 'quán ship bù' || $normalized === 'quan ship bu';
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
