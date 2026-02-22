@extends('layouts.app')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
    <span class="text-6xl" aria-hidden="true">🔒</span>
    <div class="mt-4 text-7xl font-bold text-gray-300 dark:text-gray-600">403</div>
    <h1 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Bạn không có quyền truy cập nội dung này</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400 max-w-md">
        @if($exception->getMessage())
            {{ $exception->getMessage() }}
        @else
            Tài nguyên tồn tại nhưng tài khoản của bạn không được phép truy cập.
        @endif
    </p>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-500">Nếu bạn nghĩ đây là nhầm lẫn, vui lòng liên hệ quản trị viên.</p>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition font-medium">
            Về Dashboard
        </a>
        <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium">
            Trang chủ
        </a>
    </div>
</div>
@endsection
