<div class="header" style="border-bottom-color: {{ $primaryColor }};">
    <div class="brand">
        @if($clinic['logo_data_uri'])
            <img class="logo" src="{{ $clinic['logo_data_uri'] }}" alt="{{ $clinic['display_name'] }}">
        @else
            <strong class="brand-name" style="color: {{ $primaryColor }};">{{ $clinic['display_name'] }}</strong>
        @endif
    </div>
    <div class="report-title">
        <div class="eyebrow" style="color: {{ $primaryColor }};">Relatório de bioimpedância</div>
        <h1 style="color: {{ $secondaryColor }};">{{ $pageTitle }}</h1>
        <div>Avaliação Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }} · Seção {{ $pageNumber }} de 3</div>
    </div>
</div>
