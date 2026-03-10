@extends('layouts.cong-viec')

@section('congViecContent')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $returnUrl ?? route('cong-viec', ['tab' => $returnTab]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                {{ $returnLabel ?? 'Quay lại công việc' }}
            </a>
        </div>
        @include('pages.cong-viec.partials.task-detail-content', ['task' => $task, 'inModal' => false, 'returnTab' => $returnTab ?? 'tong-quan'])
    </div>
@endsection
