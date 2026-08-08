@php
    $scale = $indicator['scale'] ?? null;
    $segments = $scale['segments'] ?? [];
    $pending = $indicator['pending'] ?? false;
    $classNameToColor = function ($className) {
        return match ($className) {
            'bg-blue-500' => 'blue',
            'bg-emerald-500' => 'emerald',
            'bg-amber-500' => 'amber',
            'bg-rose-500' => 'rose',
            default => 'slate',
        };
    };
@endphp
<div class="label">{{ $title }}</div>
<div class="metric-value">{{ $value }}</div>
<span class="badge {{ $indicator['tone'] ?? 'neutral' }}">{{ $indicator['classification'] ?? '-' }}</span>
@if($scale)
    <div class="pointer-row">
        @unless($pending)
            <div class="pointer" style="left: {{ $scale['position'] ?? 50 }}%;"></div>
        @endunless
    </div>
    <div class="scale">
        @foreach($segments as $segment)
            <div class="segment {{ $pending ? 'slate' : $classNameToColor($segment['className'] ?? '') }}" style="width: {{ $segment['width'] ?? 0 }}%;"></div>
        @endforeach
    </div>
    <table class="legend">
        <tr>
            @foreach($segments as $segment)
                <td style="width: {{ $segment['width'] ?? 0 }}%;">{{ $segment['label'] ?? '' }}</td>
            @endforeach
        </tr>
    </table>
@endif
