@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('thu-chi', ['tab' => 'lich-su']) }}" class="text-theme-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white">← Thu chi</a>
        <span class="text-gray-400 dark:text-gray-500">/</span>
        <h1 class="text-theme-xl font-semibold text-gray-900 dark:text-white">Sửa thu ước tính</h1>
    </div>

    @if(session('error'))
        <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-800 dark:border-error-800 dark:bg-error-900/30 dark:text-error-200">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-800 dark:border-error-800 dark:bg-error-900/30 dark:text-error-200">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white max-w-md">
        <form method="POST" action="{{ route('thu-chi.income.update', $income->id) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <select name="source_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach($sources as $s)
                    <option value="{{ $s->id }}" {{ old('source_id', $income->source_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ old('date', $income->date->format('Y-m-d')) }}" required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <input type="text" name="amount" value="{{ old('amount', (int) $income->amount) }}" placeholder="Số tiền" required inputmode="numeric" data-format-vnd
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <input type="text" name="note" value="{{ old('note', $income->note) }}" placeholder="Ghi chú"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-600">Lưu</button>
                <a href="{{ route('thu-chi', ['tab' => 'lich-su']) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-theme-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Hủy</a>
            </div>
        </form>
    </div>
</div>
@endsection
