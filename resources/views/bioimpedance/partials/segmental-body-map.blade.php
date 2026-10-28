@php
    $positions = [
        ['arm', 'left', 'Braço E', 8, 88, 91, 105],
        ['arm', 'right', 'Braço D', 252, 88, 169, 105],
        ['trunk', 'center', 'Tronco', 252, 174, 153, 170],
        ['leg', 'left', 'Perna E', 8, 285, 108, 255],
        ['leg', 'right', 'Perna D', 252, 285, 152, 255],
    ];
    $states = [
        'low' => ['Abaixo', '↓', '#2563eb'],
        'standard' => ['Padrão', '●', '#047857'],
        'high' => ['Alto', '↑', '#be123c'],
    ];
    $items = collect($positions)->map(function ($position) use ($measurements, $type, $states) {
        [$region, $side, $label, $textX, $textY, $lineX, $lineY] = $position;
        $measurement = collect($measurements)->first(fn ($item) => ($item['type'] ?? null) === $type && ($item['region'] ?? null) === $region && ($item['side'] ?? null) === $side) ?? [];
        $state = $states[$measurement['classification'] ?? ''] ?? ['Não identificado', '?', '#475569'];
        return compact('region', 'side', 'label', 'textX', 'textY', 'lineX', 'lineY', 'measurement', 'state');
    });
    $pdfNumber = fn ($value, $suffix = '') => $value === null || $value === '' ? '—' : number_format((float) $value, 1, ',', '.').$suffix;
@endphp
<div class="segmental-map keep-together">
    <div class="segmental-title">{{ $title }}</div>
    <div class="segmental-subtitle">Vista frontal · L = esquerdo · R = direito</div>
    <svg viewBox="0 0 260 350" class="segmental-svg" role="img" aria-label="{{ $title }} por região corporal">
        <g fill="#f8e8eb" stroke="#9f5364" stroke-width="1.3">
            <circle cx="130" cy="39" r="20"/>
            <path d="M111 64 C116 58 144 58 149 64 L160 134 C157 157 149 175 147 187 L113 187 C111 175 103 157 100 134 Z"/>
            <path d="M104 70 C91 80 86 99 83 120 L74 187 C72 198 79 203 85 193 L97 134 L115 80 Z"/>
            <path d="M156 70 C169 80 174 99 177 120 L186 187 C188 198 181 203 175 193 L163 134 L145 80 Z"/>
            <path d="M114 186 C108 208 104 239 103 274 L99 333 C100 343 110 343 114 334 L126 274 L130 205 L130 187 Z"/>
            <path d="M146 186 C152 208 156 239 157 274 L161 333 C160 343 150 343 146 334 L134 274 L130 205 L130 187 Z"/>
        </g>
        <path d="M130 64V187M104 129H156M130 187V275" stroke="#c98998" stroke-width="1" stroke-dasharray="3 3" fill="none"/>
        <text x="88" y="209" fill="#7f1d3f" font-size="10" font-weight="700">L</text><text x="166" y="209" fill="#7f1d3f" font-size="10" font-weight="700">R</text>
        @foreach($items as $item)
            @php($left = $item['side'] === 'left')
            <path d="M{{ $item['lineX'] }} {{ $item['lineY'] }} L{{ $left ? $item['textX'] + 42 : $item['textX'] - 42 }} {{ $item['textY'] }}" stroke="{{ $item['state'][2] }}" stroke-width="1" fill="none"/>
            <circle cx="{{ $item['lineX'] }}" cy="{{ $item['lineY'] }}" r="3" fill="{{ $item['state'][2] }}"/>
            <text x="{{ $item['textX'] }}" y="{{ $item['textY'] - 12 }}" text-anchor="{{ $left ? 'start' : 'end' }}" fill="#334155" font-size="7" font-weight="700">{{ $item['label'] }}</text>
            <text x="{{ $item['textX'] }}" y="{{ $item['textY'] }}" text-anchor="{{ $left ? 'start' : 'end' }}" fill="#172033" font-size="8" font-weight="700">{{ $pdfNumber($item['measurement']['mass_kg'] ?? null, ' kg') }}</text>
            <text x="{{ $item['textX'] }}" y="{{ $item['textY'] + 11 }}" text-anchor="{{ $left ? 'start' : 'end' }}" fill="{{ $item['state'][2] }}" font-size="7" font-weight="700">{{ $item['state'][1] }} {{ $item['state'][0] }}</text>
        @endforeach
    </svg>
    <table class="segmental-table">
        <thead><tr><th>Região/lado</th><th>kg</th><th>% ref.</th><th>Estado</th></tr></thead>
        <tbody>@foreach($items as $item)<tr><th>{{ $item['label'] }}</th><td>{{ $pdfNumber($item['measurement']['mass_kg'] ?? null) }}</td><td>{{ $pdfNumber($item['measurement']['reference_percentage'] ?? null) }}</td><td style="color: {{ $item['state'][2] }}; font-weight: 700;">{{ $item['state'][1] }} {{ $item['state'][0] }}</td></tr>@endforeach</tbody>
    </table>
</div>
