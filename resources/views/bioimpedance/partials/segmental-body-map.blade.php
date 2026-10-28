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
    $itemsByRegion = $items->keyBy(fn ($item) => $item['region'].'|'.$item['side']);
    $pdfNumber = fn ($value, $suffix = '') => $value === null || $value === '' ? '—' : number_format((float) $value, 1, ',', '.').$suffix;
    $silhouette = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 300"><g fill="#f8e8eb" stroke="#9f5364" stroke-width="2"><circle cx="60" cy="27" r="20"/><path d="M43 52 C49 46 71 46 77 52 L87 125 C83 148 76 164 74 177 L46 177 C44 164 37 148 33 125 Z"/><path d="M38 58 C24 72 20 96 18 122 L10 180 C9 194 19 198 25 185 L35 126 L50 65 Z"/><path d="M82 58 C96 72 100 96 102 122 L110 180 C111 194 101 198 95 185 L85 126 L70 65 Z"/><path d="M47 176 C41 200 38 226 38 253 L34 292 C36 302 46 302 49 292 L58 250 L60 192 L60 177 Z"/><path d="M73 176 C79 200 82 226 82 253 L86 292 C84 302 74 302 71 292 L62 250 L60 192 L60 177 Z"/></g><path d="M60 51V177M34 121H86M60 177V252" stroke="#c98998" stroke-width="1.5" stroke-dasharray="4 4" fill="none"/><text x="24" y="205" fill="#7f1d3f" font-family="DejaVu Sans" font-size="12" font-weight="700">L</text><text x="89" y="205" fill="#7f1d3f" font-family="DejaVu Sans" font-size="12" font-weight="700">R</text></svg>';
    $silhouetteDataUri = 'data:image/svg+xml;base64,'.base64_encode($silhouette);
@endphp
<div class="segmental-map keep-together">
    <div class="segmental-title">{{ $title }}</div>
    <div class="segmental-subtitle">Vista frontal · L = esquerdo · R = direito</div>
    <table class="segmental-visual" role="presentation">
        @foreach([
            ['arm|left', 'arm|right'],
            [null, 'trunk|center'],
            ['leg|left', 'leg|right'],
        ] as $rowIndex => $keys)
            <tr>
                @php($leftItem = $keys[0] ? $itemsByRegion->get($keys[0]) : null)
                <td class="segmental-value segmental-value-right">
                    @if($leftItem)<strong>{{ $leftItem['label'] }}</strong><br>{{ $pdfNumber($leftItem['measurement']['mass_kg'] ?? null, ' kg') }}<br>{{ $pdfNumber($leftItem['measurement']['reference_percentage'] ?? null, '% ref.') }}<br><span style="color:{{ $leftItem['state'][2] }}; font-weight:700;">{{ $leftItem['state'][1] }} {{ $leftItem['state'][0] }}</span>@endif
                </td>
                @if($rowIndex === 0)<td class="body-column" rowspan="3"><img class="segmental-silhouette" src="{{ $silhouetteDataUri }}" alt="Silhueta corporal frontal com marcações L e R"></td>@endif
                @php($rightItem = $itemsByRegion->get($keys[1]))
                <td class="segmental-value"><strong>{{ $rightItem['label'] }}</strong><br>{{ $pdfNumber($rightItem['measurement']['mass_kg'] ?? null, ' kg') }}<br>{{ $pdfNumber($rightItem['measurement']['reference_percentage'] ?? null, '% ref.') }}<br><span style="color:{{ $rightItem['state'][2] }}; font-weight:700;">{{ $rightItem['state'][1] }} {{ $rightItem['state'][0] }}</span></td>
            </tr>
        @endforeach
    </table>
    <table class="segmental-table">
        <caption>Equivalente textual de {{ mb_strtolower($title) }}</caption>
        <thead><tr><th>Região/lado</th><th>kg</th><th>% ref.</th><th>Estado</th></tr></thead>
        <tbody>@foreach($items as $item)<tr><th>{{ $item['label'] }}</th><td>{{ $pdfNumber($item['measurement']['mass_kg'] ?? null) }}</td><td>{{ $pdfNumber($item['measurement']['reference_percentage'] ?? null) }}</td><td style="color: {{ $item['state'][2] }}; font-weight: 700;">{{ $item['state'][1] }} {{ $item['state'][0] }}</td></tr>@endforeach</tbody>
    </table>
</div>
