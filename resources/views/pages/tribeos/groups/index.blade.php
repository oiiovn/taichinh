@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('tribeos.groups.create') }}" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:focus:ring-brand-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tạo nhóm
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 text-theme-sm text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
        @endif

        @if($groups->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-10 text-center dark:border-gray-800 dark:bg-gray-900/50">
                <p class="text-theme-sm text-gray-600 dark:text-gray-400">Bạn chưa tham gia nhóm nào.</p>
                <a href="{{ route('tribeos.groups.create') }}" class="mt-4 inline-flex rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white hover:bg-brand-600">Tạo nhóm đầu tiên</a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($groups as $group)
                    <a href="{{ route('tribeos.groups.show', $group->slug) }}" class="block rounded-xl border border-gray-200 bg-white p-5 shadow-theme-xs transition hover:border-brand-300 hover:shadow-theme-sm dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-brand-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $group->name }}</h3>
                        @if($group->description)
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{{ $group->description }}</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">{{ $group->members->count() }} thành viên</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
