@extends('layouts.cong-viec')

@section('congViecContent')
<div class="mx-auto max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Chương trình hành vi</h1>
        <a href="{{ route('cong-viec.programs.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Tạo chương trình
        </a>
    </div>
    @if(session('success'))
        <p class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('success') }}</p>
    @endif
    @if($programs->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Chưa có chương trình. Tạo chương trình để gắn công việc và theo dõi tiến độ (mục tiêu, thời hạn, độ nhất quán).</p>
    @else
        <ul class="space-y-3">
            @foreach($programs as $p)
                <li>
                    <a href="{{ route('cong-viec.programs.show', $p->id) }}" class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:hover:border-brand-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-900 dark:text-white">{{ $p->title }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $p->start_date->format('d/m/Y') }} → {{ $p->getEndDateResolved()->format('d/m/Y') }} · {{ $p->getDaysTotal() }} ngày
                                    · <span class="capitalize">{{ $p->status }}</span>
                                </p>
                            </div>
                            <span class="text-brand-600 dark:text-brand-400">Xem →</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
