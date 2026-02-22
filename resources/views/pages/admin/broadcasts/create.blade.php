@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Tạo thông báo</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.broadcasts.index') }}">Thông báo</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Tạo</span>
        </nav>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 max-w-xl">
        <form action="{{ route('admin.broadcasts.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tiêu đề *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="VD: Bảo trì hệ thống 22/02">
                    @error('title')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nội dung</label>
                    <textarea name="body" rows="4" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Nội dung chi tiết (có thể để trống)">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Loại</label>
                    <select name="type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="info" {{ old('type', 'info') === 'info' ? 'selected' : '' }}>Thông tin</option>
                        <option value="feature" {{ old('type') === 'feature' ? 'selected' : '' }}>Tính năng mới</option>
                        <option value="maintenance" {{ old('type') === 'maintenance' ? 'selected' : '' }}>Bảo trì</option>
                        <option value="urgent" {{ old('type') === 'urgent' ? 'selected' : '' }}>Khẩn</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Đối tượng nhận</label>
                    <select name="target_type" id="target_type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="all" {{ old('target_type', 'all') === 'all' ? 'selected' : '' }}>Toàn hệ thống</option>
                        <option value="plan" {{ old('target_type') === 'plan' ? 'selected' : '' }}>Theo gói</option>
                        <option value="feature" {{ old('target_type') === 'feature' ? 'selected' : '' }}>Theo tính năng</option>
                    </select>
                </div>
                <div id="target_value_wrap" style="{{ old('target_type', 'all') === 'all' ? 'display:none' : '' }}">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Giá trị đối tượng</label>
                    <select id="target_value_plan" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" style="{{ old('target_type') === 'plan' ? '' : 'display:none' }}" @if(old('target_type') === 'plan') name="target_value" @endif>
                        <option value="">— Chọn gói —</option>
                        @foreach($plansList as $key => $info)
                            <option value="{{ $key }}" {{ old('target_value') === $key ? 'selected' : '' }}>{{ $info['name'] ?? $key }}</option>
                        @endforeach
                    </select>
                    <select id="target_value_feature" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" style="{{ old('target_type') === 'feature' ? '' : 'display:none' }}" @if(old('target_type') === 'feature') name="target_value" @endif>
                        <option value="">— Chọn tính năng —</option>
                        @foreach($featureList as $key => $label)
                            <option value="{{ $key }}" {{ old('target_value') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Khi chọn "Theo gói" hoặc "Theo tính năng", chỉ user thuộc gói/tính năng đó mới thấy thông báo.</p>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Gửi thông báo</button>
                <a href="{{ route('admin.broadcasts.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Hủy</a>
            </div>
        </form>
    </div>
    <script>
        (function(){
            var targetType = document.getElementById('target_type');
            var wrap = document.getElementById('target_value_wrap');
            var planSel = document.getElementById('target_value_plan');
            var featureSel = document.getElementById('target_value_feature');
            if (!targetType) return;
            function sync() {
                var v = targetType.value;
                wrap.style.display = v === 'all' ? 'none' : 'block';
                planSel.style.display = v === 'plan' ? 'block' : 'none';
                featureSel.style.display = v === 'feature' ? 'block' : 'none';
                if (v !== 'plan') planSel.removeAttribute('name'); else planSel.name = 'target_value';
                if (v !== 'feature') featureSel.removeAttribute('name'); else featureSel.name = 'target_value';
            }
            targetType.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
