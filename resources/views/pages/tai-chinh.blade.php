@extends('layouts.tai-chinh')

@section('taiChinhContent')
@php
        $validTabs = ['dashboard', 'tai-khoan', 'giao-dich', 'phan-tich', 'chien-luoc', 'nguong-ngan-sach', 'no-khoan-vay'];
        $tab = in_array(request('tab'), $validTabs) ? request('tab') : 'dashboard';
    @endphp
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(!empty($load_error))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ $load_error_message ?? 'Đã xảy ra lỗi khi tải dữ liệu. Vui lòng thử lại sau.' }}</div>
    @else
    @if($tab === 'dashboard')
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dashboard</h2>
            @include('pages.tai-chinh.partials.dashboard-the-lien-ket')
        </div>
    @elseif($tab === 'tai-khoan')
        @include('pages.tai-chinh.tai-khoan')
    @elseif($tab === 'giao-dich')
        @include('pages.tai-chinh.lich-su-giao-dich')
    @elseif($tab === 'phan-tich')
        @include('pages.tai-chinh.phan-tich')
    @elseif($tab === 'chien-luoc')
        @include('pages.tai-chinh.chien-luoc')
    @elseif($tab === 'nguong-ngan-sach')
        @include('pages.tai-chinh.nguong-ngan-sach')
    @elseif($tab === 'no-khoan-vay')
        @include('pages.tai-chinh.no-khoan-vay')
    @endif
    @endif
@endsection

@section('taiChinhRightColumn')
    @if($tab === 'chien-luoc')
        @include('pages.tai-chinh.partials.chien-luoc.hanh-trinh-timeline', [
            'timelineSnapshots' => $timelineSnapshots ?? [],
            'timelineMaturity' => $timelineMaturity ?? 'new',
        ])
    @endif
@endsection
