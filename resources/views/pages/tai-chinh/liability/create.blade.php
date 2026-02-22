@extends(request('embed') ? 'layouts.embed' : 'layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @if(!request('embed'))
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex items-center gap-2 text-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Nợ & Khoản vay</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <span class="text-gray-800 dark:text-white">Thêm khoản nợ / vay</span>
        </nav>
    </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="mx-auto max-w-xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-white/[0.03]">
        <h2 class="mb-6 text-xl font-semibold text-gray-900 dark:text-white">Thêm khoản nợ / khoản vay hoặc khoản cho vay</h2>
        @include('pages.tai-chinh.liability.partials.form', ['inModal' => false])
    </div>
@endsection
