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
use App\Services\Food\MaterialConsumptionService;
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

        // Gom theo ngày đơn (Thời gian): dán nhiều ngày → tạo nhiều BC, mỗi ngày 1 báo cáo như logic cũ
        $rowsByDate = $this->groupRowsByOrderDate($rows);
        ksort($rowsByDate);

        $created = [];
        $consumeNotes = [];

        foreach ($rowsByDate as $reportDate => $dayRows) {
            $report = $this->createSalesReportFromRows(
                (int) $user->id,
                $branchId,
                $reportDate,
                $dayRows,
                $productPrices
            );
            $created[] = $report;

            try {
                if (! $report->food_branch_id) {
                    $consumeNotes[] = $report->report_code.': chưa chọn CN';
                } else {
                    $applied = app(MaterialConsumptionService::class)->applyReportConsumption($report);
                    if ($applied['applied_rows'] > 0) {
                        $consumeNotes[] = $report->report_code.': trừ '.$applied['applied_rows'].' NL';
                    } elseif ($applied['no_recipe'] > 0 || $applied['missing_products'] > 0) {
                        $consumeNotes[] = $report->report_code.': thiếu CT/SP';
                    }
                }
            } catch (\Throwable $e) {
                $consumeNotes[] = $report->report_code.': lỗi trừ NL';
            }
        }

        $buffMsg = $buffCount > 0 ? ' (+'.$buffCount.' đơn Quán Ship Bù)' : '';
        $consumeMsg = $consumeNotes !== [] ? ' '.implode('; ', $consumeNotes).'.' : '';

        if (count($created) === 1) {
            $report = $created[0];

            return redirect()
                ->route('food.bao-cao-ban-hang.show', $report)
                ->with('success', 'Đã tạo báo cáo '.$report->report_code.' ('.$report->report_date->format('d/m/Y').')'.$buffMsg.$consumeMsg);
        }

        $summary = collect($created)
            ->map(fn (FoodSalesReport $r) => $r->report_code.' '.$r->report_date->format('d/m/Y'))
            ->implode(', ');

        return redirect()
            ->route('food.bao-cao-ban-hang')
            ->with('success', 'Đã tạo '.count($created).' báo cáo theo ngày: '.$summary.$buffMsg.'.'.$consumeMsg);
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

        $materialConsumption = app(MaterialConsumptionService::class)->breakdownForReport($report);

        return view('pages.food.bao-cao-ban-hang-show', [
            'title' => 'Chi tiết báo cáo '.$report->report_code,
            'report' => $report,
            'orders' => $ordersArray,
            'display_total_cost' => $recalculatedTotalCost,
            'display_total_tien_cong' => $recalculatedTienCong,
            'users' => $users,
            'canManage' => $canManage,
            'branches' => $branches,
            'materialConsumption' => $materialConsumption,
        ]);
    }

    public function applyMaterialConsumption(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $report = FoodSalesReport::query()->where('user_id', $user->id)->find($id);
        if (! $report) {
            return redirect()->route('food.bao-cao-ban-hang')->with('error', 'Không tìm thấy báo cáo.');
        }

        if (! $report->food_branch_id) {
            return redirect()
                ->route('food.bao-cao-ban-hang.show', $report)
                ->with('error', 'Chọn chi nhánh cho báo cáo trước khi trừ tồn NL.');
        }

        try {
            $result = app(MaterialConsumptionService::class)->applyReportConsumption($report);
        } catch (\Throwable $e) {
            return redirect()
                ->route('food.bao-cao-ban-hang.show', $report)
                ->with('error', $e->getMessage());
        }

        $parts = [];
        if ($result['applied_rows'] > 0) {
            $parts[] = 'Đã trừ '.$result['applied_rows'].' NL/bao bì kho chi nhánh theo công thức × SL bán';
        } else {
            $parts[] = 'Không có NL nào được trừ';
        }
        if ($result['no_recipe'] > 0) {
            $parts[] = $result['no_recipe'].' mã thiếu công thức';
        }
        if ($result['missing_products'] > 0) {
            $parts[] = $result['missing_products'].' mã chưa có trong Sản phẩm';
        }

        return redirect()
            ->route('food.bao-cao-ban-hang.show', $report)
            ->with('success', implode('. ', $parts).'.');
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

        app(MaterialConsumptionService::class)->reverseReportConsumption($report);
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
        $onlyTienCongKhungGio = $request->boolean('only_tien_cong_khung_gio');
        if ($onlyTienCongKhungGio) {
            $onlyTienCong = true;
        }
        $bonus = (float) ($report->bonus ?? 0);
        if ($onlyTienCongKhungGio) {
            $baseAmount = $this->calculateLaborAmountByTimeWindow($report, '16:30', '22:00');
        } else {
            $baseAmount = $onlyTienCong
                ? (float) $report->total_tien_cong + $bonus
                : (float) $report->total_cost + (float) $report->total_tien_cong + $bonus;
        }

        $deductionAmount = (float) $request->input('deduction_amount', 0);
        if ($deductionAmount < 0 || $deductionAmount > $baseAmount) {
            return redirect()->back()->with('error', 'Số tiền trừ công nợ phải từ 0 đến '.number_format($baseAmount, 0, ',', '.').' đ.');
        }
        $deductionAmount = (int) round($deductionAmount);
        $additionAmount = (float) $request->input('addition_amount', 0);
        if ($additionAmount < 0) {
            return redirect()->back()->with('error', 'Số tiền cộng công nợ phải lớn hơn hoặc bằng 0.');
        }
        $additionAmount = (int) round($additionAmount);

        FoodReportDebt::query()->updateOrCreate(
            [
                'food_sales_report_id' => $report->id,
                'debtor_user_id' => $debtorUserId,
            ],
            [
                'only_tien_cong' => $onlyTienCong,
                'only_tien_cong_khung_gio' => $onlyTienCongKhungGio,
                'deduction_amount' => $deductionAmount,
                'addition_amount' => $additionAmount,
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

        $doanhSo = $request->input('doanh_so');
        if ($doanhSo !== null && trim((string) $doanhSo) !== '') {
            $report->doanh_so = VndHelper::toStoredAmount($doanhSo);
        } else {
            $report->doanh_so = null;
        }

        $phiBuff = $request->input('phi_buff');
        if ($phiBuff !== null && trim((string) $phiBuff) !== '') {
            $report->phi_buff = VndHelper::toStoredAmount($phiBuff);
        } else {
            $report->phi_buff = null;
        }

        $phiAds = $request->input('phi_ads');
        if ($phiAds !== null && trim((string) $phiAds) !== '') {
            $report->phi_ads = VndHelper::toStoredAmount($phiAds);
        } else {
            $report->phi_ads = null;
        }
        $report->save();

        return response()->json([
            'success' => true,
            'doanh_so' => $report->doanh_so,
            'phi_buff' => $report->phi_buff,
            'phi_ads' => $report->phi_ads,
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

    /**
     * Gom dòng theo ngày đơn hàng. Mỗi hóa đơn thuộc 1 ngày (theo Thời gian muộn nhất của đơn).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>  key = Y-m-d
     */
    private function groupRowsByOrderDate(array $rows): array
    {
        $invoiceDate = [];
        foreach ($rows as $row) {
            $invoice = (string) ($row['ma_hoa_don'] ?? '');
            if ($invoice === '') {
                continue;
            }
            if (! empty($row['thoi_gian'])) {
                $dt = $this->parseThoiGian((string) $row['thoi_gian']);
                if ($dt) {
                    $date = $dt->toDateString();
                    if (! isset($invoiceDate[$invoice]) || $date > $invoiceDate[$invoice]) {
                        $invoiceDate[$invoice] = $date;
                    }
                }
            }
        }

        $fallback = now()->toDateString();
        $grouped = [];
        foreach ($rows as $row) {
            $invoice = (string) ($row['ma_hoa_don'] ?? '');
            $date = $invoiceDate[$invoice] ?? $fallback;
            $grouped[$date][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<string, FoodProduct>  $productPrices
     */
    private function createSalesReportFromRows(
        int $userId,
        ?int $branchId,
        string $reportDate,
        array $rows,
        $productPrices
    ): FoodSalesReport {
        $maHoaDonSet = [];
        $orderCosts = [];
        foreach ($rows as $row) {
            $don = (string) ($row['ma_hoa_don'] ?? '');
            $maHoaDonSet[$don] = true;
            $maHang = $row['ma_hang'] ?? '';
            $sl = (float) ($row['sl'] ?? $row['sl_ban'] ?? 0);
            $giaVon = $productPrices->get($maHang)?->gia_von ?? 0;
            $orderCosts[$don] = ($orderCosts[$don] ?? 0) + ((float) $giaVon * $sl);
        }

        $totalCost = array_sum($orderCosts);
        $totalTienCong = 0;
        foreach ($orderCosts as $donCost) {
            $totalTienCong += $donCost > self::NGUONG_VON_TIEN_CONG_CAO ? self::TIEN_CONG_CAO : self::TIEN_CONG_THAP;
        }

        $bonus = FoodReportBonusTier::getBonusForTotalCost($totalCost);
        $nextCode = $this->nextReportCode($userId);

        $report = FoodSalesReport::query()->create([
            'user_id' => $userId,
            'food_branch_id' => $branchId,
            'report_code' => $nextCode,
            'report_date' => $reportDate,
            'total_orders' => count($maHoaDonSet),
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

        return $report;
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

    private function calculateLaborAmountByTimeWindow(FoodSalesReport $report, string $fromTime, string $toTime): float
    {
        $fromMinutes = $this->minutesFromTimeString($fromTime);
        $toMinutes = $this->minutesFromTimeString($toTime);
        if ($fromMinutes === null || $toMinutes === null) {
            return 0.0;
        }

        $items = $report->relationLoaded('items')
            ? $report->items
            : $report->items()->get();

        $orders = [];
        foreach ($items as $item) {
            $invoice = trim((string) ($item->ma_hoa_don ?? ''));
            if ($invoice === '') {
                $invoice = '_';
            }
            if (! isset($orders[$invoice])) {
                $orders[$invoice] = [
                    'order_minutes' => null,
                    'total_cost' => 0.0,
                ];
            }

            $parsedTime = $this->parseThoiGian((string) ($item->thoi_gian ?? ''));
            if ($parsedTime) {
                $minutes = ((int) $parsedTime->format('H') * 60) + (int) $parsedTime->format('i');
                if ($orders[$invoice]['order_minutes'] === null || $minutes > $orders[$invoice]['order_minutes']) {
                    $orders[$invoice]['order_minutes'] = $minutes;
                }
            }

            $giaVon = (float) ($item->gia_von_unit ?? 0);
            $sl = (float) ($item->sl ?? $item->sl_ban ?? 0);
            $orders[$invoice]['total_cost'] += $giaVon * $sl;
        }

        $totalLabor = 0.0;
        foreach ($orders as $order) {
            $minutes = $order['order_minutes'];
            if ($minutes === null || $minutes < $fromMinutes || $minutes > $toMinutes) {
                continue;
            }

            $totalLabor += $order['total_cost'] > self::NGUONG_VON_TIEN_CONG_CAO
                ? self::TIEN_CONG_CAO
                : self::TIEN_CONG_THAP;
        }

        return $totalLabor;
    }

    private function minutesFromTimeString(string $time): ?int
    {
        $time = trim($time);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}
