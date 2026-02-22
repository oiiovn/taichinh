{{-- Tổng quan: layout thích ứng theo stage hành vi (Interface Adaptation Engine) --}}
@php
    $adapt = $interfaceAdaptation ?? [];
    $layout = $adapt['layout'] ?? 'guided';
@endphp
@switch($layout)
    @case('focus')
        @include('pages.cong-viec.partials.tong-quan-focus')
        @break
    @case('analytic')
        @include('pages.cong-viec.partials.tong-quan-analytic')
        @break
    @case('strategic')
        @include('pages.cong-viec.partials.tong-quan-strategic')
        @break
    @default
        @include('pages.cong-viec.partials.tong-quan-guided')
@endswitch
