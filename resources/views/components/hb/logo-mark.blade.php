@props([
    'size' => 'md',
])

@php
    $box = match ($size) {
        'sm' => 'h-6 w-6 rounded-md',
        'lg' => 'h-9 w-9 rounded-lg',
        default => 'h-8 w-8 rounded-lg',
    };

    $icon = match ($size) {
        'sm' => 12,
        'lg' => 20,
        default => 18,
    };
@endphp

<div {{ $attributes->merge(['class' => "flex shrink-0 items-center justify-center bg-gradient-to-br from-[#1fcc8a] to-[#12a870] {$box}"]) }}>
    <svg width="{{ $icon }}" height="{{ $icon }}" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M9 2L3 8l6 8 6-8-6-6z" fill="white" fill-opacity="0.95"/>
        <path d="M6 8l3 4 3-4" stroke="white" stroke-width="1.2" stroke-linecap="round" fill="none"/>
    </svg>
</div>
