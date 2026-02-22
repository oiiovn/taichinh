@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Thông báo</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('dashboard') }}">Trang chủ</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Thông báo</span>
        </nav>
    </div>

    @php
        $typeLabels = ['maintenance' => 'Bảo trì', 'feature' => 'Tính năng', 'info' => 'Thông tin', 'urgent' => 'Khẩn'];
        $readAtMap = $readAtMap ?? collect();
    @endphp

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <ul class="divide-y divide-gray-200 dark:divide-gray-800">
            @forelse ($broadcasts as $b)
                @php $readAt = $readAtMap->get($b->id); @endphp
                <li>
                    <a href="{{ route('thong-bao.show', $b) }}" class="flex gap-4 px-5 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                            @if(($b->type ?? '') === 'urgent' || ($b->type ?? '') === 'maintenance')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @else
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-800 dark:text-white/90 {{ !$readAt ? 'font-semibold' : '' }}">{{ $b->title }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $typeLabels[$b->type] ?? $b->type }} · {{ $b->created_at->format('d/m/Y H:i') }}</p>
                            @if($b->body)
                                <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ Str::limit(strip_tags($b->body), 120) }}</p>
                            @endif
                        </div>
                        @if(!$readAt)
                            <span class="shrink-0 rounded-full bg-primary/20 px-2 py-0.5 text-xs font-medium text-primary">Mới</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">Chưa có thông báo nào.</li>
            @endforelse
        </ul>
        @if ($broadcasts->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $broadcasts->links() }}</div>
        @endif
    </div>
@endsection
