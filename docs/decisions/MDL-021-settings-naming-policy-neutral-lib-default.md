---
id: MDL-021
title: 'SettingsNamingPolicy: default neutro de lib, prefixo real do produto injetado pelo consumidor'
status: accepted
date: 2026-07-31
lang: pt-BR
domains: [moodle, settings]
deciders: ['Michael Meneses']
related: [MDL-011, MDL-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: '`SettingsNamingPolicy::DEFAULT_PREFIX` deixa de ser `mdg_` (marca MIDDAG hardcoded) e passa a ser `mdglib_` — um valor neutro que só identifica "produzido por um consumidor desta policy sem prefixo configurado", nunca a identidade de um produto específico. O prefixo real da MIDDAG (`mdg_`) não desaparece: passa a ser injetado explicitamente pelo composition root de cada produto (ex. `middag-io/core`), nunca herdado em silêncio do default da lib.'
---

# MDL-021: SettingsNamingPolicy — default neutro de lib, prefixo real do produto injetado pelo consumidor

## Context

`middag-io/moodle` é OSS Apache-2.0, destinado a qualquer consumidor terceiro
que construa um plugin Moodle sobre o adapter — não só produtos MIDDAG. Até
esta decisão, `SettingsNamingPolicy::DEFAULT_PREFIX` era literalmente `mdg_`:
a marca MIDDAG hardcoded como default silencioso de um pacote genérico. Isso é
um problema de design para uma lib OSS por dois motivos: (1) um terceiro que
não configura o próprio prefixo herda silenciosamente a marca de outra
empresa nas suas próprias config keys; (2) dois terceiros diferentes que não
configurem nada colidem entre si sob o mesmo prefixo `mdg_`.

O mecanismo de extensibilidade já existia antes desta decisão —
`SettingsNamingPolicy` sempre aceitou um prefixo custom via construtor
(`new SettingsNamingPolicy('clientx_')`), e é isso que o teste
`clientPolicyProducesClientKeysWithoutAffectingTheDefault` cobre desde antes
deste ADR (seam **LB-HOST-EXT-01**). O que faltava corrigir não era a
capacidade de override — já existia — e sim **o valor do default** quando
ninguém configura nada.

**Achado relevante durante a análise deste ADR, registrado aqui porque os
dois lados do fix (mudar o default da lib + garantir injeção explícita do
lado do produto) precisam estar cobertos, mesmo que o segundo lado seja
código em outro repo:**

- `middag-io/core` (`src/Adapter/Moodle/Runtime/ContainerFactory.php:684`) já
  registra o `SettingsResolver` no container injetando
  `new SettingsNamingPolicy('mdg_')` **explicitamente**, com um comentário que
  cita `core#64 W7` e o motivo exato deste ADR ("Injects the real MIDDAG
  prefix explicitly instead of relying on the OSS lib's zero-arg constructor
  default"). Esse caminho — a maioria das leituras/escritas de settings, via
  `SettingsResolver`/`AbstractSetting`/`SettingsSupport` quando passam pelo
  container — já está seguro.
- **Mas `middag-io/core` (`src/Adapter/Moodle/Admin/Status/MoodleSettingsReader.php:34`)
  NÃO está.** O construtor faz `$this->policy = $policy ?? new SettingsNamingPolicy();`
  — fallback pro default da lib, sem prefixo explícito. E os três call sites
  em `src/Adapter/Moodle/Admin/MoodleAdminFactory.php` (linhas 99, 135, 176)
  só passam o primeiro argumento (`$config->settingsExtension`), nunca o
  segundo (`$policy`) — então esse leitor específico (usado pela página de
  Admin Status) resolve hoje para `mdg_*` só porque o default da lib
  **ainda** é `mdg_`. No momento em que `middag-io/moodle` publicar esta
  mudança e `middag-io/core` atualizar sua dependência sem primeiro corrigir
  esse construtor, `MoodleSettingsReader` passa a ler `mdglib_core_apikey`
  em vez de `mdg_core_apikey` — a mesma chave gravada por
  `SettingsResolver`/`SettingsSupport` (que continuam corretos via
  `ContainerFactory`) fica invisível para a tela de Admin Status. Este é um
  gap real, verificado nesta sessão, **não corrigido por este ADR** (fica em
  `middag-io/core`, repo separado, fora do escopo deste commit) — é
  pré-condição de release, não detalhe cosmético.

**Tensão com um ADR existente, não resolvida aqui:** [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md)
afirma "The `mdg_` prefix is a fixed framework constant, not adaptable per
plugin" — frase já desatualizada em relação ao código antes mesmo deste ADR
(a policy sempre aceitou override via construtor), e que este ADR torna
ainda mais desatualizada ao trocar o próprio valor do default. MDL-011 é uma
reconstrução de arquivo legado do `moodle-local_middag` (ver nota de
proveniência no próprio documento) e não é revisado por este ADR — fica
registrado aqui como discrepância conhecida para uma correção futura de
MDL-011, não resolvida por ora.

## Considered Options

1. **Manter `mdg_` como default da lib** — rejeitado: é exatamente o problema
   que motivou este ADR — marca de um cliente específico vazando como
   comportamento silencioso de um pacote Apache-2.0 genérico.
2. **Remover o default por completo — construtor sem argumento lança
   exceção, todo consumidor é obrigado a passar prefixo** — rejeitado: quebra
   a ergonomia de um default de biblioteca razoável (terceiro que só quer um
   prefixo qualquer, sem noção de marca, continua servido por um valor
   neutro pronto); e não é a forma da decisão já tomada pelo dono.
3. **Default neutro (`mdglib_`), sem marca de ninguém; produto real injeta o
   próprio prefixo explicitamente no composition root** (escolhida) — mantém
   a ergonomia de ter um default funcional, remove a marca MIDDAG do
   comportamento silencioso, e não tira a capacidade de override que já
   existia.

## Decision

- `SettingsNamingPolicy::DEFAULT_PREFIX` muda de `mdg_` para `mdglib_`. O
  valor é neutro por construção: identifica "consumidor desta policy sem
  prefixo configurado", nunca uma marca.
- O prefixo real da MIDDAG (`mdg_`) não desaparece — continua existindo, mas
  como valor **injetado explicitamente** por quem monta um produto real sobre
  este adapter (hoje, `middag-io/core`), nunca herdado em silêncio do
  default da lib. Este ADR não resolve a instanciação em
  `middag-io/core`/`MoodleSettingsReader` (ver Context) — fica documentado
  como pré-condição de release, endereçada em commit próprio nesse outro
  repo.
- **Extensibilidade não muda**: os pontos de injeção continuam aceitando
  override por parâmetro opcional (`SettingsResolver::__construct`,
  `SettingsSupport::get/set/unset`, `ThemeSupport::isInheritanceEnabled`,
  `AbstractSetting::useNamingPolicy`) — nenhuma capacidade de um consumidor
  passar seu próprio prefixo foi removida ou restringida por este ADR.
- Esta é uma **mudança de comportamento** (breaking) para qualquer consumidor
  que dependia do default sem configurar o próprio prefixo — documentado no
  commit como `fix(settings)!:` com `Release-As` explícito (política `1.x`
  de `middag-io/moodle`, ver `CLAUDE.md`/`API-STABILITY.md`).

## Consequences

- Um terceiro que instale `middag-io/moodle` do zero e não configure nada
  passa a ver `mdglib_*` nas suas config keys, não `mdg_*` — comportamento
  correto e esperado para um pacote OSS genérico.
- Qualquer consumidor MIDDAG (ou de terceiros) que já estava em produção
  contando com o default silencioso da lib para produzir `mdg_*` **quebra**
  se não injetar `mdg_` (ou o prefixo que já usava) explicitamente antes de
  atualizar a dependência — é exatamente o `MoodleSettingsReader` descrito em
  Context.
- `ContainerFactory` do `middag-io/core` já está correto (core#64 W7,
  anterior a este ADR) — nenhuma ação necessária ali.
- `MoodleSettingsReader` do `middag-io/core` **precisa** do mesmo tratamento
  (`new SettingsNamingPolicy('mdg_')` explícito) antes que este pacote seja
  atualizado em produção — rastreado como pré-condição de release, não como
  parte deste commit.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| `DEFAULT_PREFIX` é `mdglib_`, sem marca de nenhum produto | `tests/Settings/SettingsNamingPolicyTest.php` (`defaultPolicyProducesTheNeutralLibraryKeys`) | done |
| Nenhum ponto de injeção perdeu a capacidade de override | `tests/Settings/SettingsNamingPolicyTest.php::clientPolicyProducesClientKeysWithoutAffectingTheDefault`, `AbstractSettingCoverageTest::useNamingPolicyOverridesTheDefaultPolicyUsedByResolveConfigName` | done |
| `middag-io/core` injeta `mdg_` explicitamente em todo ponto que hoje usa o default da lib | `ContainerFactory.php:684` confere; `MoodleSettingsReader.php:34` (via `MoodleAdminFactory.php:99,135,176`) **não confere** | pending — bloqueia release, fora deste repo |
| MDL-011 continua afirmando prefixo "fixed... not adaptable per plugin" | Nenhuma verificação automatizada; discrepância registrada acima, não corrigida por este ADR | planned |
