<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Trang chủ' }} | AI Social</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tribeos-home { font-family: Inter, "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .tribeos-home-scroll::-webkit-scrollbar { height: 6px; width: 6px; }
        .tribeos-home-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="tribeos-home min-h-screen bg-[#eef1f6] text-[#1c1e21] antialiased">
@php
    $user = auth()->user();
    $displayName = $user?->name ?? 'Bạn';
    $avatarUrl = $user?->avatar_url;
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $pendingInvitationCount = (int) ($pendingInvitationCount ?? 0);
@endphp

<header class="sticky top-0 z-50 border-b border-[#e4e6eb] bg-white/95 backdrop-blur-md">
    <div class="mx-auto flex h-[60px] max-w-[1440px] items-center gap-3 px-3 sm:px-4 lg:px-6">
        <a href="{{ route('tribeos') }}" class="flex shrink-0 items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-white shadow-sm">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </span>
            <span class="hidden text-lg font-bold tracking-tight text-[#1c1e21] sm:inline">AI Social</span>
        </a>

        <form action="{{ route('tribeos') }}" method="get" class="mx-auto hidden min-w-0 flex-1 max-w-xl md:block">
            <label class="relative block">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#65676b]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm bạn bè, bài viết, chủ đề..." class="w-full rounded-full border-0 bg-[#f0f2f5] py-2.5 pl-11 pr-4 text-[15px] text-[#1c1e21] placeholder:text-[#65676b] focus:outline-none focus:ring-2 focus:ring-[#5b7cfa]/30" />
            </label>
        </form>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            <a href="{{ route('tribeos') }}" title="Trang chủ" class="flex h-10 w-10 items-center justify-center rounded-full {{ request()->routeIs('tribeos') ? 'bg-[#e7f0ff] text-[#1877f2]' : 'bg-[#e4e6eb] text-[#050505] hover:bg-[#d8dadf]' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9.5 12 4l9 5.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5z"/></svg>
            </a>
            <a href="{{ route('thong-bao.index') }}" title="Thông báo" class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[#e4e6eb] text-[#050505] hover:bg-[#d8dadf]">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0"/></svg>
            </a>
            <a href="{{ route('tribeos.invitations.index') }}" title="Lời mời nhóm" class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[#e4e6eb] text-[#050505] hover:bg-[#d8dadf]">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                @if($pendingInvitationCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#f02849] px-1 text-[10px] font-bold text-white">{{ $pendingInvitationCount > 9 ? '9+' : $pendingInvitationCount }}</span>
                @endif
            </a>
            <a href="{{ route('tribeos.groups.create') }}" class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e4e6eb] text-[#050505] hover:bg-[#d8dadf]" title="Tạo nhóm">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            </a>
            <a href="{{ route('profile') }}" class="ml-1 flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-[#f0f2f5]">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="h-9 w-9 rounded-full object-cover ring-2 ring-white" />
                @else
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-sm font-semibold text-white">{{ $initial }}</span>
                @endif
                <span class="hidden max-w-[100px] truncate text-sm font-semibold lg:inline">{{ $displayName }}</span>
                <svg class="hidden h-4 w-4 text-[#65676b] lg:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </a>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-[1440px] items-start gap-4 px-2 py-4 sm:px-4 lg:gap-6 lg:px-6">
    @include('pages.tribeos.partials.home-left-sidebar')

    <main class="min-w-0 flex-1 max-w-[680px] pb-24 lg:pb-8">
        @yield('tribeosHomeMain')
    </main>

    @include('pages.tribeos.partials.home-right-sidebar')
</div>

<footer class="fixed bottom-0 left-0 z-40 flex items-center gap-3 px-4 py-2 text-xs text-[#65676b] lg:static lg:px-6 lg:pb-6">
    <button type="button" onclick="document.documentElement.classList.toggle('dark')" class="flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-[#e4e6eb]" title="Giao diện">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4M5.6 18.4l1.4-1.4m10-10 1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg>
    </button>
    <span>© 2025 AI Social</span>
</footer>

@stack('scripts')
<x-ui.confirm-delete />
</body>
</html>
