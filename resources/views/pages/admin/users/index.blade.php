@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Quản lý user</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Quản lý user</span>
        </nav>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ showConfirmDelete: false, formIdToSubmit: null }" @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId" @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="font-medium text-gray-800 dark:text-white">Danh sách user</h3>
            <a href="{{ route('admin.users.create') }}" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Thêm user</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">ID</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Tên</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Email</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Admin</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $u->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ $u->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $u->email }}</td>
                            <td class="px-4 py-3">
                                @if ($u->is_admin)
                                    <span class="rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/20 dark:text-success-400">Admin</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.brain.monitor', $u) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Brain</a>
                                    <a href="{{ route('admin.users.edit', $u) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Sửa</a>
                                    @if ($u->id !== auth()->id())
                                        <form id="form-delete-user-{{ $u->id }}" action="{{ route('admin.users.destroy', $u) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-user-{{ $u->id }}' })" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Xóa</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có user nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $users->links() }}</div>
        @endif
        <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa user" defaultMessage="Bạn có chắc muốn xóa user này?" />
    </div>
@endsection
