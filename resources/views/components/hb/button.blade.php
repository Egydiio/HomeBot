@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0b0d] disabled:pointer-events-none disabled:opacity-60';

    $sizeClass = match ($size) {
        'sm' => 'rounded-lg px-3 py-2 text-xs',
        'lg' => 'rounded-[10px] px-8 py-3.5 text-base',
        'nav' => 'rounded-lg px-5 py-2.5 text-sm',
        default => 'rounded-lg px-5 py-2.5 text-sm',
    };

    $variantClass = match ($variant) {
        'primary' => 'bg-[#1fcc8a] text-[#0e0f11] hover:opacity-90 focus-visible:ring-[#1fcc8a]/50',
        'secondary' => 'border border-[#1d2028] bg-transparent text-[#737a8a] hover:border-[#2a2f3a] hover:text-[#eef0f5] focus-visible:ring-[#1fcc8a]/30',
        'secondary-muted' => 'border border-[#1d2028] bg-transparent text-[#737a8a] hover:border-[#404858] hover:text-[#eef0f5] focus-visible:ring-[#1fcc8a]/30',
        'outline-light' => 'border border-[#1d2028] bg-transparent text-[#eef0f5] hover:border-[#2a2f3a] focus-visible:ring-[#1fcc8a]/30',
        default => 'bg-[#1fcc8a] text-[#0e0f11] hover:opacity-90 focus-visible:ring-[#1fcc8a]/50',
    };

    $classes = trim("{$base} {$sizeClass} {$variantClass}");
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
