# Ativos vetoriais de bioimpedância

## Mapa corporal segmentar Vita Scan

`vita-scan-segmental-body-front.svg` é o ativo vetorial frontal usado como base para a apresentação de dados segmentares.

- O desenho é original do projeto Vita Scan e não reproduz a silhueta, a malha, as texturas ou os gradientes dos relatórios Fitdays.
- Sua finalidade é exclusivamente informativa e não diagnóstica.
- O arquivo tem fundo transparente e usa contornos que permanecem distinguíveis em fundos claros e em impressão em escala de cinza.
- Cabeça e pescoço pertencem ao grupo `body-context` e não representam regiões mensuradas.

### Contrato das regiões

Os caminhos que podem receber estado visual possuem IDs estáveis e atributos `data-region` e `data-side`:

| ID | Região | Lado |
| --- | --- | --- |
| `region-arm-left` | Braço | Esquerdo |
| `region-arm-right` | Braço | Direito |
| `region-trunk-center` | Tronco | Centro |
| `region-leg-left` | Perna | Esquerdo |
| `region-leg-right` | Perna | Direito |

### Âncoras para rótulos

O grupo `annotation-anchors` contém pontos invisíveis para conectar linhas e textos sem depender do contorno do desenho:

| ID | Coordenada no `viewBox` |
| --- | --- |
| `anchor-arm-left` | `98, 245` |
| `anchor-arm-right` | `262, 245` |
| `anchor-trunk-center` | `180, 270` |
| `anchor-leg-left` | `145, 465` |
| `anchor-leg-right` | `215, 465` |

As mesmas coordenadas estão registradas no bloco `metadata` do SVG.

Na interface React, o contrato de regiões, coordenadas, estados, paleta e marcadores laterais fica centralizado em `resources/js/components/bioimpedance/segmentalBodyVisual.js`. O componente `SegmentalBodyFigure.jsx` referencia os caminhos deste SVG; ele não mantém uma segunda cópia da geometria corporal.

### Personalização

O ativo aceita as seguintes propriedades CSS, todas com valores de fallback:

- `--vita-body-fill`
- `--vita-body-stroke`
- `--vita-body-detail`
- `--vita-body-separator`
- `--vita-body-print-fill`
- `--vita-body-print-stroke`
- `--vita-body-print-detail`

As cores representam somente o estado fornecido pelo aplicativo de origem. A camada de apresentação não deve inferir diagnóstico, risco ou alteração clínica a partir delas.

### Modos de apresentação

- **Gordura segmentar:** utiliza textura vetorial suave por região, mantendo o valor e o percentual como informações principais.
- **Equilíbrio muscular:** utiliza preenchimento sólido para facilitar a comparação visual entre esquerda e direita.

Os dois modos usam exatamente a mesma geometria. Massa, percentual e classificação nunca deformam ou redimensionam partes do corpo, e nenhuma diferença visual deve ser descrita automaticamente como diagnóstico ou assimetria clínica.

### Responsividade e acessibilidade

- Em telas compactas, a figura exibe o corpo sem linhas comprimidas e os dados regionais aparecem em cartões logo abaixo.
- A partir do breakpoint `sm`, linhas, pontos e rótulos voltam a compor o mapa completo.
- Em telas amplas, gordura segmentar e equilíbrio muscular formam duas colunas.
- A legenda usa rótulos abreviados somente no celular e preserva o nome completo para tecnologia assistiva.
- A tabela textual permanece disponível, possui região rolável com foco por teclado e é a fonte acessível equivalente aos desenhos.
- Valor ausente é apresentado como travessão e classificação ausente usa explicitamente `Não identificado`.
