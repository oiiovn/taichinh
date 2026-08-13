@extends('layouts.fullscreen-layout')

@section('content')
@php
    $inputClass = 'h-12 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm text-gray-800 placeholder:text-gray-400 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-orange-500 dark:focus:ring-orange-900/40';
@endphp
<div class="min-h-screen bg-[#F4F5F7] dark:bg-gray-950 lg:grid lg:grid-cols-[minmax(0,42%)_minmax(0,58%)]">
    {{-- Left: branding --}}
    <aside class="relative hidden flex-col overflow-hidden bg-[#FBF7F2] px-10 py-8 lg:flex dark:bg-gray-900">
        <a href="{{ route('signin') }}" class="relative z-10 flex items-center gap-3">
            <img src="{{ asset('images/auth/fresh-logo.png') }}" alt="FRESH" class="h-12 w-12 rounded-2xl object-cover shadow-sm ring-1 ring-black/5">
            <span class="leading-tight">
                <span class="block text-lg font-extrabold tracking-wide text-gray-900 dark:text-white">FRESH</span>
                <span class="block text-sm text-gray-500 dark:text-gray-400">Bánh Tráng Trộn</span>
            </span>
        </a>

        <div class="relative z-10 mx-auto flex w-full max-w-md flex-1 items-center justify-center py-6">
            <img src="{{ asset('images/auth/banh-trang-tron-hero.png') }}" alt="Bánh tráng trộn FRESH" class="max-h-[52vh] w-full object-contain drop-shadow-sm">
        </div>

        <div class="relative z-10 max-w-md">
            <h2 class="text-3xl font-extrabold leading-tight tracking-tight text-gray-900 dark:text-white">
                Quản lý dễ dàng / Kinh doanh <span class="text-[#FF7A1A]">hiệu quả</span>
            </h2>
            <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                Hệ thống quản lý kho, đơn hàng và doanh thu dành cho chuỗi cửa hàng bánh tráng trộn.
            </p>
            <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/10">
                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                </svg>
                Bảo mật & An toàn dữ liệu
            </div>
        </div>
    </aside>

    {{-- Right: login --}}
    <section class="relative flex min-h-screen flex-col px-4 py-8 sm:px-8 lg:px-12">
        <button type="button"
            class="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            @click.prevent="$store.theme.toggle()"
            title="Đổi giao diện">
            <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
            </svg>
            <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.605 7.395 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
            </svg>
        </button>

        <div class="mx-auto flex w-full max-w-[440px] flex-1 flex-col justify-center">
            <a href="{{ route('signin') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('images/auth/fresh-logo.png') }}" alt="FRESH" class="h-11 w-11 rounded-2xl object-cover ring-1 ring-black/5">
                <span class="leading-tight">
                    <span class="block font-extrabold tracking-wide text-gray-900 dark:text-white">FRESH</span>
                    <span class="block text-xs text-gray-500">Bánh Tráng Trộn</span>
                </span>
            </a>

            <div class="mb-6 text-center">
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-[28px]">Chào mừng trở lại!</h1>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Đăng nhập để tiếp tục quản lý cửa hàng</p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-[0_12px_40px_rgba(15,23,42,0.06)] sm:p-7 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-5 text-lg font-bold text-gray-900 dark:text-white">Đăng nhập</h2>

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf
                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            @foreach ($errors->all() as $err)
                                <p>{{ $err }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email / Số điện thoại</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-gray-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </span>
                            <input type="text" id="email" name="email" value="{{ old('email') }}" autocomplete="username"
                                placeholder="Nhập email hoặc số điện thoại" required
                                class="{{ $inputClass }} @error('email') border-red-500 @enderror">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mật khẩu</label>
                        <div x-data="{ showPassword: false }" class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-gray-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                </svg>
                            </span>
                            <input :type="showPassword ? 'text' : 'password'" name="password" id="password" autocomplete="current-password"
                                placeholder="Nhập mật khẩu" required
                                class="{{ $inputClass }} pr-11 @error('password') border-red-500 @enderror">
                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" tabindex="-1">
                                <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-0.5">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-[#FF7A1A] focus:ring-orange-200">
                            Ghi nhớ đăng nhập
                        </label>
                        <a href="#" class="text-sm font-semibold text-[#FF7A1A] hover:text-orange-600">Quên mật khẩu?</a>
                    </div>

                    <button type="submit"
                        class="mt-1 flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#FF7A1A] to-[#FF9A3C] px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:from-[#f06e10] hover:to-[#ff8c28]">
                        Đăng nhập
                    </button>
                </form>

                <div class="relative my-5">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-3 text-xs text-gray-400 dark:bg-gray-900">hoặc đăng nhập với</span>
                    </div>
                </div>

                <div class="space-y-2.5">
                    <button type="button"
                        class="inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M18.7511 10.1944C18.7511 9.47495 18.6915 8.94995 18.5626 8.40552H10.1797V11.6527H15.1003C15.0011 12.4597 14.4654 13.675 13.2749 14.4916L13.2582 14.6003L15.9087 16.6126L16.0924 16.6305C17.7788 15.1041 18.7511 12.8583 18.7511 10.1944Z" fill="#4285F4"/>
                            <path d="M10.1788 18.75C12.5895 18.75 14.6133 17.9722 16.0915 16.6305L13.274 14.4916C12.5201 15.0068 11.5081 15.3666 10.1788 15.3666C7.81773 15.3666 5.81379 13.8402 5.09944 11.7305L4.99473 11.7392L2.23868 13.8295L2.20264 13.9277C3.67087 16.786 6.68674 18.75 10.1788 18.75Z" fill="#34A853"/>
                            <path d="M5.10014 11.7305C4.91165 11.186 4.80257 10.6027 4.80257 9.99992C4.80257 9.3971 4.91165 8.81379 5.09022 8.26935L5.08523 8.1534L2.29464 6.02954L2.20333 6.0721C1.5982 7.25823 1.25098 8.5902 1.25098 9.99992C1.25098 11.4096 1.5982 12.7415 2.20333 13.9277L5.10014 11.7305Z" fill="#FBBC05"/>
                            <path d="M10.1789 4.63331C11.8554 4.63331 12.9864 5.34303 13.6312 5.93612L16.1511 3.525C14.6035 2.11528 12.5895 1.25 10.1789 1.25C6.68676 1.25 3.67088 3.21387 2.20264 6.07218L5.08953 8.26943C5.81381 6.15972 7.81776 4.63331 10.1789 4.63331Z" fill="#EB4335"/>
                        </svg>
                        Đăng nhập với Google
                    </button>
                    <button type="button"
                        class="inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#1877F2" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.265h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
                        </svg>
                        Đăng nhập với Facebook
                    </button>
                </div>

                <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                    Chưa có tài khoản?
                    <a href="{{ route('signup') }}" class="font-semibold text-[#FF7A1A] hover:text-orange-600">Đăng ký ngay &gt;</a>
                </p>
            </div>

            <div class="mt-8 grid grid-cols-3 gap-3 text-center sm:gap-4">
                <div class="flex flex-col items-center gap-1.5">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-[#FF7A1A] dark:bg-orange-950/40">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Báo cáo</p>
                    <p class="text-[11px] text-gray-400">Thống kê chi tiết</p>
                </div>
                <div class="flex flex-col items-center gap-1.5">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-[#FF7A1A] dark:bg-orange-950/40">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Quản lý kho</p>
                    <p class="text-[11px] text-gray-400">Kiểm soát tồn kho</p>
                </div>
                <div class="flex flex-col items-center gap-1.5">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-[#FF7A1A] dark:bg-orange-950/40">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Nhân viên</p>
                    <p class="text-[11px] text-gray-400">Quản lý dễ dàng</p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
