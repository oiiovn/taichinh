<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SanPhamController extends Controller
{
    /** Tên cột header mẫu dán (sheet) — thứ tự có thể khác, map theo tên */
    private const HEADER_MA_HANG = 'Mã hàng';

    private const HEADER_TEN_HANG = 'Tên hàng hóa';

    private const HEADER_GIA_VON = 'Giá vốn';

    /** Chuẩn hóa giá vốn VND: parse input (dấu phẩy = hàng nghìn), trả về số để lưu. Không dùng chuỗi có dấu chấm phân cách hàng nghìn. */
    private static function normalizeGiaVon(mixed $value): float
    {
        return \App\Helpers\VndHelper::parseAmount($value);
    }

    /** Giá VND lưu DB dạng số nguyên đồng. */
    private static function giaVonToStored(mixed $value): int
    {
        return \App\Helpers\VndHelper::toStoredAmount($value);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $products = FoodProduct::query()
            ->where('user_id', $user->id)
            ->orderBy('ma_hang')
            ->get();

        return view('pages.food.san-pham', [
            'title' => 'Sản phẩm',
            'products' => $products,
        ]);
    }

    /**
     * Dán mẫu từ sheet (tab-separated). Dòng đầu = header.
     * Lưu/đè theo Mã hàng (user_id + ma_hang).
     */
    public function pasteFromSheet(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $v = Validator::make($request->all(), [
            'data' => 'required|string',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => 'Thiếu dữ liệu dán'], 422);
        }

        $raw = trim((string) $request->input('data'));
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (count($lines) < 2) {
            return response()->json(['ok' => false, 'message' => 'Cần ít nhất 1 dòng header và 1 dòng dữ liệu'], 422);
        }

        $headerLine = array_shift($lines);
        $headers = $this->parseRow($headerLine);
        $idxMa = $this->findColumnIndex($headers, self::HEADER_MA_HANG);
        $idxTen = $this->findColumnIndex($headers, self::HEADER_TEN_HANG);
        $idxGia = $this->findColumnIndex($headers, self::HEADER_GIA_VON);

        if ($idxMa === null) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy cột "'.self::HEADER_MA_HANG.'" trong dòng đầu'], 422);
        }

        $saved = 0;
        $errors = [];
        foreach ($lines as $i => $line) {
            $row = $this->parseRow($line);
            if (count($row) === 0) {
                continue;
            }
            $maHang = isset($row[$idxMa]) ? trim((string) $row[$idxMa]) : '';
            if ($maHang === '') {
                continue;
            }
            $tenHang = ($idxTen !== null && isset($row[$idxTen])) ? trim((string) $row[$idxTen]) : '';
            $giaVon = 0;
            if ($idxGia !== null && isset($row[$idxGia])) {
                $giaVon = self::giaVonToStored($row[$idxGia]);
            }
            if ($giaVon < 0) {
                $giaVon = 0;
            }

            try {
                FoodProduct::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'ma_hang' => $maHang,
                    ],
                    [
                        'ten_hang' => $tenHang,
                        'gia_von' => $giaVon,
                    ]
                );
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = 'Dòng '.($i + 2).': '.$e->getMessage();
            }
        }

        $products = FoodProduct::query()
            ->where('user_id', $user->id)
            ->orderBy('ma_hang')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'ma_hang' => $p->ma_hang,
                'ten_hang' => $p->ten_hang,
                'gia_von' => (float) $p->gia_von,
                'is_combo' => (bool) $p->is_combo,
            ]);

        return response()->json([
            'ok' => true,
            'saved' => $saved,
            'errors' => array_slice($errors, 0, 10),
            'products' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $v = Validator::make($request->all(), [
            'ma_hang' => 'required|string|max:100',
            'ten_hang' => 'nullable|string|max:255',
            'gia_von' => 'nullable',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => $v->errors()->first()], 422);
        }

        $maHang = trim($request->input('ma_hang'));
        $tenHang = trim($request->input('ten_hang', ''));
        $giaVon = self::giaVonToStored($request->input('gia_von', 0));

        $product = FoodProduct::query()->updateOrCreate(
            ['user_id' => $user->id, 'ma_hang' => $maHang],
            ['ten_hang' => $tenHang, 'gia_von' => $giaVon]
        );

        return response()->json(['ok' => true, 'product' => $product->fresh()]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $product = FoodProduct::query()->where('user_id', $user->id)->find($id);
        if (! $product) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $v = Validator::make($request->all(), [
            'ma_hang' => 'sometimes|string|max:100',
            'ten_hang' => 'nullable|string|max:255',
            'gia_von' => 'nullable',
            'is_combo' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => $v->errors()->first()], 422);
        }

        if ($request->has('ma_hang')) {
            $product->ma_hang = trim($request->input('ma_hang'));
        }
        if ($request->has('ten_hang')) {
            $product->ten_hang = trim($request->input('ten_hang', ''));
        }
        if ($request->has('gia_von')) {
            $product->gia_von = self::giaVonToStored($request->input('gia_von'));
        }
        if ($request->has('is_combo')) {
            $product->is_combo = (bool) $request->input('is_combo');
        }
        $product->save();

        return response()->json(['ok' => true, 'product' => $product->fresh()]);
    }

    public function bulkGiaVon(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $v = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'gia_von' => 'required',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => $v->errors()->first()], 422);
        }

        $ids = array_map('intval', $request->input('ids'));
        $giaVon = self::giaVonToStored($request->input('gia_von'));

        $updated = FoodProduct::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update(['gia_von' => $giaVon]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $product = FoodProduct::query()->where('user_id', $user->id)->find($id);
        if (! $product) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->delete();

        return response()->json(['ok' => true]);
    }

    private function parseRow(string $line): array
    {
        return array_map('trim', explode("\t", $line));
    }

    private function findColumnIndex(array $headers, string $name): ?int
    {
        $name = trim($name);
        foreach ($headers as $i => $h) {
            if (trim($h) === $name) {
                return $i;
            }
        }

        return null;
    }
}
