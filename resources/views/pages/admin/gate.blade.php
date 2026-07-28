@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 mx-auto max-w-md md:p-6
@endsection

@section('content')
    <div class="mx-auto mt-10 max-w-md">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Mở khóa Admin</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nhập mật khẩu để vào khu vực quản trị</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.gate.unlock') }}" class="space-y-4" autocomplete="off">
                @csrf
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mật khẩu</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Nhập mật khẩu mở khóa"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                        Vào Admin
                    </button>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                        Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
