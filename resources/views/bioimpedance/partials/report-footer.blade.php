@php
    $configuredFooter = trim((string) ($clinic['footer_text'] ?? ''));
    $footerContent = $configuredFooter !== ''
        ? $configuredFooter
        : trim($clinic['display_name'].' · '.($clinic['contact'] ?? ''), ' ·');
@endphp
<div class="document-footer">
    {{ $footerContent }}
    <span class="footer-right">Emitido em {{ $issuedAt?->format('d/m/Y H:i') }} · Relatório Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}</span>
</div>
