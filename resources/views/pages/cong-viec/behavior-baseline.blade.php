@extends('layouts.cong-viec')

@section('congViecContent')
    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    <div class="mx-auto max-w-2xl">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">Bản ngã hành vi (Identity Baseline)</h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Giúp hệ thống so sánh bạn với chính bạn, không với người khác. Điều chỉnh khi cần.</p>
        <form action="{{ route('cong-viec.behavior-baseline.update') }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label for="chronotype" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nhịp sinh học (chronotype)</label>
                <select name="chronotype" id="chronotype" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">-- Chọn --</option>
                    @foreach($chronotypes as $value => $label)
                        <option value="{{ $value }}" @selected($baseline && $baseline->chronotype === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sleep_stability_score" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Độ ổn định giờ ngủ (0 = rất lệch, 1 = rất ổn định)</label>
                <input type="number" name="sleep_stability_score" id="sleep_stability_score" min="0" max="1" step="0.1" value="{{ $baseline ? $baseline->sleep_stability_score : '' }}" placeholder="0–1" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label for="energy_amplitude" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Biên độ dao động năng lượng (0 = đều, 1 = dao động mạnh)</label>
                <input type="number" name="energy_amplitude" id="energy_amplitude" min="0" max="1" step="0.1" value="{{ $baseline ? $baseline->energy_amplitude : '' }}" placeholder="0–1" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label for="procrastination_pattern" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mẫu trì hoãn điển hình</label>
                <select name="procrastination_pattern" id="procrastination_pattern" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">-- Chọn --</option>
                    @foreach($procrastination_options as $value => $label)
                        <option value="{{ $value }}" @selected($baseline && $baseline->procrastination_pattern === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stress_response" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Phản ứng với áp lực</label>
                <select name="stress_response" id="stress_response" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">-- Chọn --</option>
                    @foreach($stress_options as $value => $label)
                        <option value="{{ $value }}" @selected($baseline && $baseline->stress_response === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="behavior_events_consent" value="1" @checked($user->behavior_events_consent ?? false) class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Cho phép ghi nhận hành vi vi mô (tốc độ mở app, thời điểm tick, thời gian xem) để cải thiện gợi ý.</span>
                </label>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500">Lưu</button>
                <a href="{{ route('cong-viec') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Quay lại</a>
            </div>
        </form>
    </div>
@endsection
