@extends('layouts.fullscreen-layout')

@section('content')
@php
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($scanUrl ?? '') . '&margin=10';
@endphp
<div class="mx-auto w-full max-w-xl rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">QR nhận quà 5 sao</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Quét mã để mở trang nhận quà cho khách hàng.</p>

    <div class="mt-5 flex justify-center">
        <img src="{{ $qrUrl }}" alt="QR nhận quà 5 sao" class="rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700">
    </div>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $scanUrl ?? '' }}</p>

    <a href="{{ $scanUrl ?? '#' }}" class="mt-5 inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
        Mở trang nhận quà
    </a>
</div>
@endsection

