@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Mã QR đăng ký</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">QR đăng ký</span>
        </nav>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white">{{ $presetLabel }}</p>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            In hoặc hiển thị mã để người dùng quét; họ sẽ vào trang đăng ký và sau khi tạo tài khoản chỉ có quyền Food — Thống kê seeding (đúng cấu hình preset). URL có chữ ký, không tự sửa tham số.
        </p>
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-start">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/40 [&_svg]:max-h-[280px] [&_svg]:w-auto">
                {!! $qrSvg !!}
            </div>
            <div class="min-w-0 flex-1 space-y-2">
                <label class="block text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Liên kết (sao chép)</label>
                <input type="text" readonly value="{{ $signupUrl }}"
                    class="w-full break-all rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-200"
                    onclick="this.select()" />
            </div>
        </div>
    </div>
@endsection
