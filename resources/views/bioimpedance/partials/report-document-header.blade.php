<div class="document-header">
    <div class="document-brand">
        @if($clinic['logo_data_uri'])
            <img class="document-logo" src="{{ $clinic['logo_data_uri'] }}" alt="{{ $clinic['display_name'] }}">
        @else
            <strong class="document-brand-name" style="color: {{ $primaryColor }};">{{ $clinic['display_name'] }}</strong>
        @endif
    </div>
    <div class="document-identification">
        <div class="eyebrow" style="color: {{ $primaryColor }};">Relatório de bioimpedância</div>
        <strong>{{ $client['full_name'] }}</strong>
        <div>Avaliação Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
