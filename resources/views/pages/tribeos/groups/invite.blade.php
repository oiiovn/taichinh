@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="space-y-6" x-data="{
        q: '',
        selected: null,
        results: [],
        loading: false,
        open: false,
        searchUrl: '{{ route('tribeos.groups.search-users', $group->slug) }}',
        async search() {
            const term = this.q.trim();
            if (term.length < 2) { this.results = []; this.open = false; return; }
            this.loading = true;
            try {
                const r = await fetch(this.searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                this.results = await r.json();
                this.open = true;
            } catch (e) { this.results = []; }
            this.loading = false;
        },
        select(user) {
            this.selected = user;
            this.q = user.name + ' (' + user.email + ')';
            this.open = false;
        },
        clear() {
            this.selected = null;
            this.q = '';
            this.results = [];
        }
    }" @click.away="open = false">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Mời thành viên vào {{ $group->name }}</h1>

        @if(session('error'))
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tribeos.groups.invite.store', $group->slug) }}" method="post" class="max-w-xl space-y-4">
            @csrf
            <input type="hidden" name="user_id" :value="selected ? selected.id : ''">

            <div>
                <label class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tìm thành viên (tên hoặc email) <span class="text-error-500">*</span></label>
                <div class="relative mt-1">
                    <input type="text"
                        x-model="q"
                        @input.debounce.200ms="search()"
                        @focus="if (results.length) open = true"
                        @keydown.escape="open = false"
                        placeholder="Gõ tên hoặc email để tìm..."
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 pr-20 text-theme-sm shadow-theme-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        autocomplete="off">
                    <button type="button" x-show="selected" @click="clear()" class="absolute right-2 top-1/2 -translate-y-1/2 text-theme-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Bỏ chọn</button>
                </div>
                <div class="relative" x-show="open && (results.length > 0 || loading)" x-transition class="mt-0.5">
                    <div class="max-h-48 overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
                        <template x-if="loading">
                            <div class="px-3 py-4 text-center text-theme-sm text-gray-500">Đang tìm...</div>
                        </template>
                        <template x-if="!loading && results.length">
                            <ul class="py-1">
                                <template x-for="u in results" :key="u.id">
                                    <li>
                                        <button type="button" @click="select(u)" class="w-full px-3 py-2 text-left text-theme-sm hover:bg-gray-100 dark:hover:bg-gray-700" x-text="u.name + ' — ' + u.email"></button>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Gõ ít nhất 2 ký tự. Người được mời sẽ nhận thông báo và có thể xác nhận hoặc từ chối.</p>
            </div>

            <div>
                <label for="role" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Vai trò khi tham gia</label>
                <select name="role" id="role" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm shadow-theme-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="member" {{ old('role', 'member') === 'member' ? 'selected' : '' }}>Thành viên</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Quản trị</option>
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" :disabled="!selected" class="rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed">Gửi lời mời</button>
                <a href="{{ route('tribeos.groups.show', $group->slug) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Quay lại</a>
            </div>
        </form>
    </div>
@endsection
