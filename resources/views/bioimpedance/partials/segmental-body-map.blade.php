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
    $silhouetteDataUri = $segmentalBodyImages[$type] ?? null;
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
                @if($rowIndex === 0)<td class="body-column" rowspan="3">@if($silhouetteDataUri)<img class="segmental-silhouette" src="{{ $silhouetteDataUri }}" alt="Mapa corporal frontal Vita Scan com regiões segmentares e marcações L e R">@endif</td>@endif
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
