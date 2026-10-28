<div class="document-header">
    <div class="document-brand">
        @if($clinic['logo_data_uri'])
            <img class="document-logo" src="{{ $clinic['logo_data_uri'] }}" alt="" aria-hidden="true">
        @endif
        <div class="document-brand-copy">
            <h1 class="document-brand-name"><span>Bio</span><em>Flow</em></h1>
            <p class="document-brand-subtitle">Análise corporal</p>
        </div>
    </div>
    <div class="document-identification">
        <div class="eyebrow" style="color: {{ $primaryColor }};">Relatório de bioimpedância</div>
        <strong>{{ $client['full_name'] }}</strong>
        <div>Avaliação Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
