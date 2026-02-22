@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('dashboard') }}">Trang chủ</a>
            <span class="text-gray-400">/</span>
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('thong-bao.index') }}">Thông báo</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90 truncate max-w-[200px]">{{ Str::limit($broadcast->title, 30) }}</span>
        </nav>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 max-w-2xl">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $broadcast->title }}</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $broadcast->created_at->format('d/m/Y H:i') }}</p>
        @if($broadcast->body)
            <div class="mt-4 prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $broadcast->body }}</div>
        @endif
        <div class="mt-6">
            <a href="{{ route('thong-bao.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">← Quay lại danh sách</a>
        </div>
    </div>
@endsection
