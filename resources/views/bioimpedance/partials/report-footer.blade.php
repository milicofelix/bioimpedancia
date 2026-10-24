<div class="footer">
    <strong>{{ $clinic['display_name'] }}</strong> · {{ $clinic['footer_text'] ?? $clinic['contact'] }}
    <span class="footer-right">Emitido em {{ $issuedAt?->format('d/m/Y H:i') }} · Relatório Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}</span>
</div>
