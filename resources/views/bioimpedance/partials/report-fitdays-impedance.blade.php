<section class="report-section">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Impedância bioelétrica', 'pageNumber' => $pageNumber, 'totalSections' => $totalSections])
    <div class="content">
        <div class="notice"><strong>Tabela técnica do equipamento.</strong> Valores em ohms preservados conforme o relatório Fitdays. Servem para rastreabilidade e comparação técnica e não geram diagnóstico.</div>
        <table class="segmental-comparison">
            <caption>Leituras segmentares por frequência e região. Lados seguem a identificação impressa no relatório de origem.</caption>
            <thead><tr><th>Frequência</th><th>Braço direito</th><th>Braço esquerdo</th><th>Tronco</th><th>Perna direita</th><th>Perna esquerda</th></tr></thead>
            <tbody>
            @foreach([20, 100] as $frequency)
                <tr>
                    <td><strong>{{ $frequency }} kHz</strong></td>
                    @foreach([['arm', 'right'], ['arm', 'left'], ['trunk', 'center'], ['leg', 'right'], ['leg', 'left']] as [$region, $side])
                        @php($reading = $impedanceByKey->get($frequency.'|'.$region.'|'.$side))
                        <td>
                            @if(($reading['impedance_ohms'] ?? null) !== null)
                                {{ $br($reading['impedance_ohms'], 1) }} Ω@if(($reading['reading_state'] ?? null) === 'uncertain') <span class="muted">(incerta)</span>@endif
                            @else
                                <span class="muted">{{ ($reading['reading_state'] ?? null) === 'unreadable' ? 'Ilegível' : 'Ausente' }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="summary"><strong>Escopo.</strong> A tabela documenta a leitura elétrica disponibilizada pelo equipamento. O PDF não deriva diagnóstico, composição adicional ou conduta clínica diretamente desses valores.</div>
    </div>
</section>
