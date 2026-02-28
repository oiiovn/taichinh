@extends('layouts.tai-chinh')

@section('taiChinhContent')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Nhóm gia đình</h2>
        @if(!empty($canCreateHousehold))
        <form method="POST" action="{{ route('tai-chinh.nhom-gia-dinh.store') }}" class="flex items-center gap-2">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Tên nhóm" maxlength="255" required
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Tạo nhóm</button>
        </form>
        @endif
    </div>
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if($households->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Bạn chưa có nhóm nào. Tạo nhóm mới hoặc được thêm vào nhóm của người khác.</p>
    @else
        <ul class="space-y-3">
            @foreach($households as $h)
                @php $isOwner = $h->owner_user_id === auth()->id(); @endphp
                <li class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                    <div>
                        <span class="font-medium">{{ $h->name }}</span>
                        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ $h->owner->name ?? '—' }}</span>
                        @if($isOwner)
                            <span class="ml-2 rounded bg-brand-100 px-2 py-0.5 text-xs text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">Chủ nhóm</span>
                        @endif
                    </div>
                    <a href="{{ route('tai-chinh.nhom-gia-dinh.show', $h->id) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Xem giao dịch</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection

@section('taiChinhRightColumn')
@endsection
