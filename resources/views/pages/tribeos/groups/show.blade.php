@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="space-y-6" x-data="{ groupView: 'overview', showConfirmLeave: false }" @confirm-leave.window="const f = document.getElementById('form-leave-group'); if (f) f.submit();">
        @if(session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 text-theme-sm text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
        @endif

        {{-- Header + nút góc phải: icon quản lý (chủ/quản trị) hoặc Xem nhóm khi đang ở manage --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white" x-show="groupView === 'overview'" x-transition>{{ $group->name }}</h1>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white" x-show="groupView === 'manage'" x-transition style="display: none;">Quản lý nhóm</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" x-show="groupView === 'overview'" x-transition>@if($group->description){{ $group->description }}@endif</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canManage)
                    <template x-if="groupView === 'overview'">
                        <button type="button" @click="groupView = 'manage'" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700" title="Quản lý nhóm">
                            {!! \App\Helpers\MenuHelper::getIconSvg('settings') !!}
                        </button>
                    </template>
                    <template x-if="groupView === 'manage'">
                        <span class="inline-flex items-center gap-2">
                            <a href="{{ route('tribeos.groups.invite', $group->slug) }}" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white hover:bg-brand-600">Mời thành viên</a>
                            <button type="button" @click="groupView = 'overview'" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Xem nhóm</button>
                        </span>
                    </template>
                @endif
                @if(!$group->isOwner(auth()->user()))
                    <form id="form-leave-group" action="{{ route('tribeos.groups.leave', $group->slug) }}" method="post" class="inline">
                        @csrf
                        <button type="button" @click="showConfirmLeave = true" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Rời nhóm</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Nội dung: Overview — Newfeed (trái) + Điều khiển / Quỹ nhóm / Timeline (phải) --}}
        <div x-show="groupView === 'overview'" x-transition class="space-y-6">
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
                {{-- Khối Newfeed (trái) --}}
                <div class="flex-1 min-w-0 space-y-4">
                    <form action="{{ route('tribeos.groups.posts.store', $group->slug) }}" method="post" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        @csrf
                        <div class="flex gap-3">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" />
                            @else
                                <div class="h-10 w-10 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <textarea name="body" rows="2" placeholder="Bạn đang nghĩ gì?" required maxlength="10000" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" {{ $errors->has('body') ? 'autofocus' : '' }}>{{ old('body') }}</textarea>
                                @error('body')
                                    <p class="mt-1 text-theme-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                <div class="mt-2 flex justify-end">
                                    <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-600">Đăng</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="space-y-4">
                        @forelse($group->posts as $post)
                            @include('pages.tribeos.partials.post-card', ['post' => $post, 'showGroupLink' => false, 'groupSlug' => $group->slug])
                        @empty
                            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa có bài đăng. Hãy viết điều gì đó!</p>
                        @endforelse
                    </div>
                </div>

                {{-- Bên phải: Điều khiển, Quỹ nhóm, Timeline --}}
                <aside class="lg:w-72 shrink-0 space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-700 dark:bg-gray-800/50">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Điều khiển</h3>
                        <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">Tạo sự kiện, đăng bài, hành động nhanh — sẽ xây dựng sau.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-700 dark:bg-gray-800/50">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Quỹ nhóm</h3>
                        <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">Sổ ghi chép đóng/chi quỹ — sẽ gắn Shared Ledger.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-700 dark:bg-gray-800/50">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Timeline</h3>
                        <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">Sự kiện, đóng tiền, bài đăng — luồng theo thời gian.</p>
                    </div>
                </aside>
            </div>
        </div>

        {{-- Nội dung: Quản lý nhóm (chỉ thay nội dung, không chuyển trang) --}}
        <div x-show="groupView === 'manage'" x-transition style="display: none;" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="text-theme-sm font-semibold text-gray-900 dark:text-white">Quản lý thành viên</h2>
                    @if($canManage)
                        <a href="{{ route('tribeos.groups.invite', $group->slug) }}" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-3 py-2 text-theme-sm font-medium text-white hover:bg-brand-600">Mời thành viên</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-theme-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Thành viên</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Vai trò</th>
                                @if($group->isOwner(auth()->user()))
                                    <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Thao tác</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($group->members as $m)
                                <tr class="bg-white dark:bg-gray-800/30">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $m->user->name ?? '—' }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $m->user->email ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($m->role === 'owner')
                                            <span class="rounded-full bg-amber-500/15 px-2 py-1 text-theme-xs font-medium text-amber-700 dark:text-amber-400">Chủ nhóm</span>
                                        @elseif($m->role === 'admin')
                                            <span class="rounded-full bg-brand-500/15 px-2 py-1 text-theme-xs font-medium text-brand-700 dark:text-brand-400">Quản trị</span>
                                        @else
                                            <span class="rounded-full bg-gray-200 px-2 py-1 text-theme-xs font-medium text-gray-700 dark:bg-gray-600 dark:text-gray-300">Thành viên</span>
                                        @endif
                                    </td>
                                    @if($group->isOwner(auth()->user()) && !$m->isOwner())
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('tribeos.groups.members.update-role', [$group->slug, $m->id]) }}" method="post" class="inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="role" value="{{ $m->role === 'admin' ? 'member' : 'admin' }}">
                                                <button type="submit" class="text-brand-600 hover:underline dark:text-brand-400">
                                                    {{ $m->role === 'admin' ? 'Hạ thành viên' : 'Lên quản trị' }}
                                                </button>
                                            </form>
                                        </td>
                                    @elseif($group->isOwner(auth()->user()))
                                        <td class="px-4 py-3 text-right">—</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <x-ui.confirm-delete openVar="showConfirmLeave" title="Xác nhận rời nhóm" defaultMessage="Bạn chắc chắn muốn rời nhóm này?" confirmText="Rời nhóm" confirmEvent="confirm-leave" />
    </div>
@endsection
