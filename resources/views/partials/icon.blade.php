@php
    $size = (int) ($size ?? 16);
    $class = trim(($class ?? '') . ' inline-block shrink-0');
    $color = $color ?? 'currentColor';
@endphp
@switch($name)
    @case('home')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="{{ $color }}" aria-hidden="true">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h4a1 1 0 001-1v-3h2v3a1 1 0 001 1h4a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
        </svg>
        @break
    @case('receipt')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <rect x="4" y="2" width="12" height="16" rx="2"/>
            <path d="M7 6h6M7 10h6M7 14h3"/>
        </svg>
        @break
    @case('wallet')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <rect x="2" y="5" width="16" height="12" rx="2"/>
            <path d="M2 9h16"/>
            <circle cx="14" cy="13" r="1" fill="{{ $color }}"/>
        </svg>
        @break
    @case('calendar')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <rect x="3" y="4" width="14" height="14" rx="2"/>
            <path d="M3 9h14M7 2v4M13 2v4"/>
        </svg>
        @break
    @case('settings')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <circle cx="10" cy="10" r="3"/>
            <path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42"/>
        </svg>
        @break
    @case('whatsapp')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="{{ $color }}" aria-hidden="true">
            <path d="M10 2C5.58 2 2 5.58 2 10c0 1.52.43 2.94 1.17 4.15L2 18l3.92-1.15A7.94 7.94 0 0010 18c4.42 0 8-3.58 8-8s-3.58-8-8-8zm3.9 11.1c-.16.46-.94.88-1.3.93-.34.05-.77.07-1.24-.08a11.2 11.2 0 01-1.13-.42 8.8 8.8 0 01-3.45-3.07c-.38-.5-.62-1.08-.64-1.67-.02-.6.18-1.11.5-1.49.14-.16.3-.2.4-.2h.29c.1 0 .24-.03.36.28.13.31.44 1.08.48 1.16.04.08.07.17.01.28-.06.1-.09.16-.18.25-.09.08-.18.19-.26.25-.08.07-.17.15-.07.3.36.62.76 1.18 1.26 1.63.5.45 1 .62 1.2.69.2.07.31.06.43-.04.12-.1.5-.59.63-.79.14-.2.27-.17.46-.1l1.45.68c.2.1.32.14.37.22.04.08.04.47-.12.93z"/>
        </svg>
        @break
    @case('pix')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="{{ $color }}" aria-hidden="true">
            <path d="M10 2L2 10l8 8 8-8-8-8zm0 3.4L15.6 11 10 16.6 4.4 11 10 5.4z"/>
        </svg>
        @break
    @case('scan')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <rect x="3" y="3" width="5" height="5" rx="1"/>
            <rect x="12" y="3" width="5" height="5" rx="1"/>
            <rect x="3" y="12" width="5" height="5" rx="1"/>
            <path d="M13 13h4v4M13 13v4"/>
        </svg>
        @break
    @case('users')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <circle cx="8" cy="7" r="3"/>
            <path d="M2 18c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
            <path d="M14 5a3 3 0 010 6M18 18c0-2.5-1.5-4.6-3.5-5.5"/>
        </svg>
        @break
    @case('chart')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <path d="M3 17l4-6 4 3 3-5 3 3"/>
        </svg>
        @break
    @case('arrow_right')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
            <path d="M5 10h10M11 6l4 4-4 4"/>
        </svg>
        @break
    @case('check')
        <svg width="{{ $size }}" height="{{ $size }}" class="{{ $class }}" viewBox="0 0 20 20" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 10l4 4 8-8"/>
        </svg>
        @break
@endswitch
