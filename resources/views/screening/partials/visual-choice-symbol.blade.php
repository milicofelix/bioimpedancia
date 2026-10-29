@php($visualKey = $visualChoiceResult['visual_key'] ?? null)
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 120 90"
    width="120"
    height="90"
    role="img"
    aria-label="Miniatura da imagem {{ $visualChoiceResult['value'] ?? '' }}: {{ $visualChoiceResult['title'] ?? 'escolha visual' }}"
    fill="none"
    stroke="#087f73"
    stroke-width="3"
    stroke-linecap="round"
    stroke-linejoin="round"
>
    @switch($visualKey)
        @case('grid_dense')
            @foreach ([18, 32, 46, 60, 74, 88, 102] as $x)<path d="M{{ $x }} 10 Q{{ $x - 4 }} 45 {{ $x }} 80" />@endforeach
            @foreach ([16, 29, 42, 55, 68, 81] as $y)<path d="M10 {{ $y }} Q60 {{ $y - 4 }} 110 {{ $y }}" />@endforeach
            @break
        @case('rings_irregular')
            <path d="M60 8 C91 7 111 28 106 52 C101 76 79 86 55 82 C29 79 12 63 15 39 C18 17 37 7 60 8Z" />
            <path d="M59 19 C81 16 96 31 94 50 C92 68 75 75 57 72 C39 70 27 59 29 43 C31 28 43 20 59 19Z" />
            <path d="M59 31 C72 29 83 38 82 49 C81 61 70 65 59 63 C48 61 42 54 43 45 C44 36 51 32 59 31Z" />
            <path d="M59 41 C66 40 71 44 71 50 C70 56 65 58 59 57 C54 56 51 53 52 48 C52 44 55 42 59 41Z" />
            @break
        @case('grid_irregular')
            @foreach ([22, 38, 55, 73, 92] as $x)<path d="M{{ $x }} 12 L{{ $x + 7 }} 79" />@endforeach
            @foreach ([18, 35, 54, 72] as $y)<path d="M13 {{ $y + 6 }} L108 {{ $y }}" />@endforeach
            @break
        @case('spirals_group')
            <path d="M25 28 C7 28 8 8 25 10 C42 12 40 31 27 35 C16 39 10 52 21 59" />
            <path d="M65 22 C47 22 48 3 65 6 C82 9 79 28 67 32 C55 36 53 50 64 55" />
            <path d="M99 29 C82 29 84 11 100 13 C114 15 113 33 102 37 C92 41 88 54 99 60" />
            <path d="M46 62 C30 62 31 45 46 47 C60 49 59 65 49 69 C40 72 38 82 46 84" />
            <path d="M82 69 C67 69 68 53 82 55 C95 57 94 72 85 75 C77 78 75 85 81 87" />
            @break
        @case('scribble')
            <path d="M60 45 C18 2 5 80 61 79 C110 78 109 8 55 11 C8 13 15 75 70 70 C108 66 101 18 57 20 C24 21 26 66 68 62 C95 59 89 29 58 29 C36 30 39 57 65 54 C80 52 77 38 59 38 C50 39 52 48 62 47" />
            @break
        @case('squares')
            @foreach ([[12, 14], [43, 8], [75, 15], [96, 7], [24, 40], [57, 35], [87, 43], [10, 67], [46, 63], [78, 68], [101, 62]] as $point)
                <rect x="{{ $point[0] }}" y="{{ $point[1] }}" width="17" height="17" />
            @endforeach
            @break
        @case('spiral_open')
            <path d="M107 13 C82 2 43 8 23 32 C1 59 25 84 59 81 C91 78 108 52 96 31 C86 13 55 15 40 32 C27 47 39 67 59 67 C77 66 87 52 81 40 C75 29 58 30 51 39 C45 47 51 56 60 55 C67 55 70 49 68 45" />
            @break
        @case('zigzag')
            <path d="M12 78 L20 12 L28 78 L36 12 L44 78 L52 12 L60 78 L68 12 L76 78 L84 12 L92 78 L100 12 L108 78" />
            @break
    @endswitch
</svg>
