@extends('layouts.cong-viec')

@section('congViecContent')
<div class="mx-auto max-w-[600px] space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cong-viec') }}" class="rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sửa công việc</h2>
    </div>
    <form action="{{ route('cong-viec.tasks.update', $task->id) }}" method="POST" class="rounded-xl border border-gray-200/80 px-4 py-2.5 dark:border-gray-600/80">
        @csrf
        @method('PUT')
        <input type="text" name="title" value="{{ old('title', $task->title) }}" placeholder="Nhập tên của công việc" required
            class="block w-full border-0 bg-transparent py-1.5 text-base font-semibold text-gray-900 placeholder-gray-400 focus:ring-0 dark:bg-transparent dark:text-white dark:placeholder-gray-500">
        <div class="mt-2">
            <textarea name="description_html" rows="4" placeholder="Ghi mô tả công việc" class="w-full resize-none rounded border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500">{{ old('description_html', $task->description_html) }}</textarea>
        </div>
        <input type="hidden" name="project_id" value="{{ old('project_id', $task->project_id) }}">
        <input type="hidden" name="priority" value="{{ old('priority', $task->priority) }}">
        <input type="hidden" name="task_due_date" value="{{ old('task_due_date', $task->due_date?->format('Y-m-d')) }}">
        <input type="hidden" name="task_due_time" value="{{ old('task_due_time', $task->due_time?->format('H:i')) }}">
        <input type="hidden" name="location" value="{{ old('location', $task->location) }}">
        <input type="hidden" name="category" value="{{ old('category', $task->category) }}">
        <input type="hidden" name="estimated_duration" value="{{ old('estimated_duration', $task->estimated_duration) }}">
        <input type="hidden" name="impact" value="{{ old('impact', $task->impact) }}">
        @foreach($task->labels->pluck('id') as $lid)
            <input type="hidden" name="label_ids[]" value="{{ $lid }}">
        @endforeach
        <div class="mt-4 flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Lưu</button>
            <a href="{{ route('cong-viec') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Hủy</a>
        </div>
    </form>
</div>
@endsection
