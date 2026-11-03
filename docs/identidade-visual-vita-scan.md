# Identidade visual Vita Scan

A paleta deriva do símbolo existente em `public_html/images/brand/bioflow-mark.png`
e da assinatura `VitaScanBrand`: azul-marinho, ciano, turquesa e acento verde.

| Uso | Cor |
| --- | --- |
| Azul-marinho, títulos e extremos dos gradientes | `#052f59` |
| Turquesa escuro, ações e cor primária configurável | `#087f98` |
| Ciano do logo, detalhes decorativos | `#159fbd` |
| Acento verde do logo | `#55ce83` |
| Fundo geral | `#f5fafc` |
| Bordas suaves | `#b2e5e6` |

Os tokens `vita-*` de `resources/css/app.css` são a referência da interface.
As classes existentes `ricosty-*` permanecem como aliases desses tokens para
compatibilidade; não representam uma segunda paleta. Novos componentes devem
preferir os tokens `vita-*`. Texto pequeno sobre fundo colorido deve usar as
variantes escuras, em vez do ciano ou verde decorativos.

Login, painel, área operacional, mapas corporais neutros, tela offline,
manifesto instalável e PDF utilizam a identidade Vita Scan. As cores de erro,
sucesso, atenção e classificação segmentar mantêm seus significados funcionais.
O PDF mantém os mapas em branco com contorno escuro para impressão econômica.

## Configurações existentes

A migration `2026_11_03_000001_apply_vita_scan_brand_colors.php` atualiza os defaults
das colunas e substitui apenas os valores salvos que correspondam aos defaults
antigos: `#d88b9a`, `#4a4a4a` e o logo Ricosty padrão. As cores personalizadas
ficam preservadas. O rollback restaura os defaults das colunas e mantém os valores
salvos, pois não é possível distinguir uma escolha manual idêntica à nova paleta.

A migration histórica de criação da tabela permanece inalterada: em instalações
novas, a migration de identidade é aplicada posteriormente.

## Publicação

Executar `php artisan migrate` para aplicar a nova identidade aos defaults salvos.
Os assets frontend precisam ser publicados pelo fluxo habitual de build/deploy.
O service worker usa `vita-scan-pwa-v7` para renovar os recursos estáticos após a
atualização. Cores de clínicas personalizadas continuam editáveis no painel admin.

## Conferência manual

1. Abrir login e painel; conferir fundos, ações, foco e cards em azul/turquesa.
2. Abrir `/app` em celular e desktop; conferir cabeçalho, navegação e opções do relatório.
3. Conferir criação, avaliação e assistente; verificar que erros continuam vermelhos e avisos continuam distinguíveis.
4. Gerar PDF com e sem histórico; conferir cores de cabeçalhos, tabelas e cards,
   mantendo a paginação existente e a legibilidade em escala de cinza.
5. Recarregar as configurações após a migration; conferir a persistência da nova paleta.
6. Conferir uma configuração personalizada: ela não deve ser substituída pela migration.
7. Atualizar o PWA e abrir a tela offline; conferir o novo fundo e a nova cor de ação.
