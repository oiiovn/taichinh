@extends('layouts.embed')

@section('content')
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-theme-sm text-warning-800 dark:border-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mx-auto max-w-xl rounded-xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
        <h2 class="mb-2 text-theme-xl font-semibold text-gray-900 dark:text-white">Tạo hợp đồng vay liên kết</h2>
        <p class="mb-6 text-theme-sm text-gray-500 dark:text-gray-400">Liên kết: cả hai bên thấy cùng dữ liệu. Nhập email bên vay nếu họ có tài khoản, hoặc tên đối tác ngoài hệ thống.</p>
        @include('pages.tai-chinh.loans.partials.form', ['inModal' => false])
    </div>
@endsection
