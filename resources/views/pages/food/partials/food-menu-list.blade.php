@php
    $initialOpenGroups = collect($navGroups)->mapWithKeys(fn ($group) => [$group['key'] => ($group['open'] ?? false)])->all();
    $appStyle = ! empty($appStyle);
@endphp
<div x-data="{ openGroups: @js($initialOpenGroups) }">
    <ul class="{{ $appStyle ? 'space-y-1' : 'space-y-0.5' }}">
        @foreach($navGroups as $group)
            @if($group['label'])
                <li class="{{ $loop->first ? 'pt-0' : ($appStyle ? 'pt-3' : 'pt-2') }}">
                    <button type="button"
                        @click="openGroups['{{ $group['key'] }}'] = !openGroups['{{ $group['key'] }}']"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-400 transition hover:bg-gray-50 active:scale-[0.99] dark:text-gray-500 dark:hover:bg-white/5">
                        <span>{{ $group['label'] }}</span>
                        <svg class="h-4 w-4 shrink-0 transition-transform" :class="openGroups['{{ $group['key'] }}'] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </li>
            @endif
            <li x-show="{{ $group['label'] ? "openGroups['{$group['key']}']" : 'true' }}"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="{{ $group['label'] ? '' : ($loop->first ? '' : 'pt-1') }}">
                <ul class="{{ $appStyle ? 'space-y-1' : 'space-y-0.5' }}">
                    @foreach($group['items'] as $item)
                        @php $isActive = $currentTab === $item['id']; @endphp
                        <li>
                            <a href="{{ $item['path'] }}"
                                @if(!empty($closeOnClick)) @click="menuOpen = false" @endif
                                class="menu-item flex items-center gap-3 rounded-xl px-3 {{ $appStyle ? 'py-2.5' : 'py-2.5' }} text-sm transition-colors {{ $isActive ? 'menu-item-active bg-brand-50 text-brand-600 shadow-sm dark:bg-brand-500/[0.14] dark:text-brand-300' : 'menu-item-inactive text-gray-700 hover:bg-gray-100 active:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                                <span class="flex shrink-0 {{ $appStyle ? 'h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800' : 'w-6 h-6' }} [&_svg]:w-4 [&_svg]:h-4 {{ $isActive && $appStyle ? 'bg-brand-100 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300' : '' }}">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                                <span class="{{ $appStyle ? 'font-medium' : '' }}">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>
</div>
