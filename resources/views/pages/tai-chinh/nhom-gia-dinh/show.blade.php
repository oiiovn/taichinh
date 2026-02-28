@extends('layouts.tai-chinh')

@section('taiChinhContent')
<div class="space-y-4" id="household-show-content">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('tai-chinh.nhom-gia-dinh.index') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">← Nhóm gia đình</a>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $household->name }}</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">Tài khoản: {{ $household->owner->name ?? '—' }}</span>
            <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                Số dư: {{ number_format($totalBalance ?? 0, 0, ',', '.') }} ₫
            </span>
        </div>
        @if($canEdit)
            <form method="POST" action="{{ route('tai-chinh.nhom-gia-dinh.members.store', $household->id) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email thành viên" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">Thêm thành viên</button>
                @error('email')<span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
            </form>
        @endif
    </div>
    @if($household->members->isNotEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Thành viên: {{ $household->members->map(fn($m) => $m->user->name ?? $m->user->email)->join(', ') }}</p>
    @endif

    <form method="GET" action="{{ route('tai-chinh.nhom-gia-dinh.show', $household->id) }}" id="form-household-giao-dich-filter" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mô tả, Số TK..."
            class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        @if(count($linkedAccountNumbers ?? []) > 1)
            <select name="stk" class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả Số TK</option>
                @foreach($linkedAccountNumbers ?? [] as $num)
                    <option value="{{ $num }}" {{ request('stk') === $num ? 'selected' : '' }}>{{ $num }}</option>
                @endforeach
            </select>
        @endif
        <select name="loai" class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả</option>
            <option value="IN" {{ request('loai') === 'IN' ? 'selected' : '' }}>Vào</option>
            <option value="OUT" {{ request('loai') === 'OUT' ? 'selected' : '' }}>Ra</option>
        </select>
        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Lọc</button>
    </form>

    <div id="giao-dich-table-container">
        @include('pages.tai-chinh.partials.giao-dich-table')
    </div>
</div>
@endsection

@section('taiChinhRightColumn')
@endsection
