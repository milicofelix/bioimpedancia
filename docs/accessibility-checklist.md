# Checklist de acessibilidade

Referência operacional: WCAG 2.2 nível AA. Este checklist orienta revisão; conformidade exige avaliação contínua com pessoas, tecnologias assistivas e critérios aplicáveis.

## Estrutura e conteúdo

- [ ] Existe um único título principal coerente e hierarquia de títulos sem saltos arbitrários.
- [ ] Regiões possuem marcos semânticos e nomes acessíveis.
- [ ] Ícones decorativos estão ocultos; ícones funcionais possuem nome acessível.
- [ ] Tabelas possuem cabeçalhos e alternativa textual para mapas/silhuetas.
- [ ] Instruções não dependem apenas de cor, posição ou formato.
- [ ] Idioma e textos são claros, sem mensagens genéricas de erro.

## Teclado e foco

- [ ] Todas as ações funcionam sem mouse.
- [ ] Ordem de foco acompanha a ordem visual e lógica.
- [ ] Foco é visível com contraste suficiente.
- [ ] Modais prendem foco, fecham com Escape e devolvem foco ao acionador.
- [ ] Não existem armadilhas de teclado.
- [ ] Atalhos ou gestos têm alternativa simples.

## Formulários e estados

- [ ] Todo campo possui rótulo persistente e associado.
- [ ] Erros aparecem próximos ao campo, explicam correção e são anunciados.
- [ ] Campos obrigatórios não são indicados somente por cor.
- [ ] Carregamento, sucesso, erro, offline e permissão insuficiente são anunciados.
- [ ] Ações irreversíveis exigem confirmação e descrevem consequência.
- [ ] Tempo limite de sessão oferece orientação compreensível.

## Visual e responsivo

- [ ] Contraste atende 4,5:1 para texto comum e 3:1 para texto grande/componentes.
- [ ] Conteúdo permanece utilizável com zoom de 200% e reflow a 320 CSS px.
- [ ] Zoom de texto e orientação do aparelho não são bloqueados.
- [ ] Conteúdo não fica atrás da navegação inferior ou safe areas.
- [ ] Nomes, números e traduções extensas não se sobrepõem.
- [ ] Movimento respeita `prefers-reduced-motion`.

## Tecnologia assistiva

- [ ] Fluxos principais foram percorridos com VoiceOver ou TalkBack.
- [ ] Desktop foi percorrido com NVDA ou VoiceOver.
- [ ] Estados dinâmicos usam `aria-live` sem anúncios repetitivos.
- [ ] Botões, abas, diálogos, status e tabelas anunciam papel, nome e estado.
- [ ] Elementos ocultos não permanecem focáveis.

## PDF

- [ ] Ordem de leitura e conteúdo textual foram conferidos no PDF.
- [ ] Mapas segmentares possuem tabela equivalente.
- [ ] Contraste permanece adequado em cor e escala de cinza.
- [ ] Documento não corta conteúdo em A4 e mantém texto selecionável.
- [ ] Limitações de marcação/tagueamento do gerador foram registradas; quando acessibilidade PDF completa for exigida, o mecanismo deve ser validado ou substituído.

## Evidência por release

Registrar data, versão, navegador, dispositivo, tecnologia assistiva, responsável, falhas encontradas e tickets corretivos. Bloquear release quando uma barreira impedir login, avaliação, revisão, relatório ou saída segura da conta.
