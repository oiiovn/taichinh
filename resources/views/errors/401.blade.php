@extends('layouts.app')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
    <span class="text-6xl" aria-hidden="true">🔐</span>
    <div class="mt-4 text-7xl font-bold text-gray-300 dark:text-gray-600">401</div>
    <h1 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Chưa đăng nhập</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400 max-w-md">
        Bạn cần đăng nhập để truy cập nội dung này.
    </p>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition font-medium">
            Đăng nhập
        </a>
        <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium">
            Trang chủ
        </a>
    </div>
</div>
@endsection
