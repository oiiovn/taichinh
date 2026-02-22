@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="space-y-6" x-data="{ showConfirmReject: false, formIdToSubmit: null }" @confirm-reject-open.window="showConfirmReject = true; formIdToSubmit = $event.detail.formId" @confirm-reject.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmReject = false">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Lời mời tham gia nhóm</h1>

        @if(session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 text-theme-sm text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
        @endif

        @if($invitations->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-10 text-center dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-theme-sm text-gray-600 dark:text-gray-400">Bạn chưa có lời mời tham gia nhóm nào.</p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach($invitations as $inv)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800/50">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $inv->inviter->name ?? '—' }} mời bạn tham gia nhóm <strong>{{ $inv->group->name }}</strong></p>
                            <p class="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">Vai trò: {{ $inv->role === 'admin' ? 'Quản trị' : 'Thành viên' }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('tribeos.invitations.accept', $inv->id) }}" method="post" class="inline">
                                @csrf
                                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white hover:bg-brand-600">Chấp nhận</button>
                            </form>
                            <form id="form-reject-inv-{{ $inv->id }}" action="{{ route('tribeos.invitations.reject', $inv->id) }}" method="post" class="inline">
                                @csrf
                                <button type="button" @click="$dispatch('confirm-reject-open', { formId: 'form-reject-inv-{{ $inv->id }}' })" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Từ chối</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-ui.confirm-delete openVar="showConfirmReject" title="Xác nhận từ chối" defaultMessage="Bạn chắc chắn từ chối lời mời?" confirmText="Từ chối" confirmEvent="confirm-reject" />
    </div>
@endsection
