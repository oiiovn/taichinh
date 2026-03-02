@extends('layouts.food')

@section('foodContent')
    @php $validTabs = ['tong-quan', 'danh-sach']; $tab = in_array(request('tab'), $validTabs) ? request('tab') : 'tong-quan'; @endphp
    @if($tab === 'tong-quan')
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Tổng quan</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Nội dung Tổng quan Food. Bạn có thể xây dựng tại đây.</p>
    @else
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Danh sách</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Nội dung Danh sách. Bạn có thể xây dựng tại đây.</p>
    @endif
@endsection
