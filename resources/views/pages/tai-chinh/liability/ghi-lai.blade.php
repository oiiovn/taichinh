@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex items-center gap-2 text-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Nợ & Khoản vay</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <span class="text-gray-800 dark:text-white">Ghi lãi thủ công</span>
        </nav>
    </div>
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="mx-auto max-w-xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-white/[0.03]">
        <h2 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">Ghi lãi thủ công / điều chỉnh</h2>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Khoản: <strong class="text-gray-800 dark:text-white">{{ $liability->name }}</strong>. Nhập số tiền lãi (dương) hoặc điều chỉnh giảm (âm).</p>
        <form method="POST" action="{{ route('tai-chinh.liability.accrual.store', $liability->id) }}" class="space-y-4">
            @csrf
            <div>
                <label for="accrual-amount" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền lãi (₫)</label>
                <input type="text" id="accrual-amount" name="amount" value="{{ old('amount') }}" required inputmode="numeric" data-format-vnd data-format-vnd-allow-negative="1"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Có thể âm để điều chỉnh giảm">
                @error('amount')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="accrual-date" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày phát sinh</label>
                <input type="date" id="accrual-date" name="accrued_at" value="{{ old('accrued_at', date('Y-m-d')) }}" required
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @error('accrued_at')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Ghi nhận</button>
            </div>
        </form>
    </div>
@endsection
