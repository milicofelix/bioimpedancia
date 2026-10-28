<div class="section-header" style="border-bottom-color: {{ $primaryColor }};">
    <div class="section-heading">
        <div class="eyebrow" style="color: {{ $primaryColor }};">Seção do relatório</div>
        <h1 style="color: {{ $secondaryColor }};">{{ $pageTitle }}</h1>
        <div>Seção {{ $pageNumber }} de {{ $totalSections ?? $pageNumber }}</div>
    </div>
</div>
