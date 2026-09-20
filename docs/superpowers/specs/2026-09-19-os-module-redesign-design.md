# Redesenho do módulo de Ordens de Serviço (OS)

Data: 2026-09-19
Ambiente: `C:\xampp\htdocs\frotaandrade` (branch `refactor-inicial`), banco **não está em produção** (10 OS de teste, 0 linhas em `man_maintenance_due`).
Referência visual: `img_modelos/Kanban Ordem de serviço.png`, `Nova OS.png`, `Nova OS continuação.png`, `lembretes manutenção preventiva.png` (produto de terceiros usado só como inspiração de fluxo/campos, não de marca/cores).

## Contexto

O módulo de OS já existe (`app/Modulos/Manutencao/{Controllers,Models,Views}/OrdensServicoController.php` etc.) com um fluxo de status "administrativo" (rascunho → aprovada → programada → em_execucao → aguardando_pecas → concluida → encerrada/cancelada) e uma tela de agenda por executante com drag-and-drop. As imagens de referência mostram um fluxo diferente: um Kanban por **estágio do processo** (Solicitação, Aguardando/Agendado, Em Execução, Análise e Aprovação) e um formulário de criação bem mais rico (medição, disponibilidade, prioridade, motivo, fornecedor, responsável, pendências, serviços com valor, anexos).

Decisões já validadas com o usuário:
1. Adotar o novo pipeline de status (substituindo o atual).
2. Adicionar os campos novos do formulário direto em `man_work_orders`, sem criar tabelas de catálogo (fornecedor e motivo ficam texto livre, não uma lista cadastrável).
3. Incluir também a tela de Lembretes (reaproveitando a tela de Vencimentos de Preventiva que já existe).

Também usar o front-end que **realmente funciona** no app: Tailwind (carregado via CDN em `layout.php`) é o único framework CSS ativo. Views que usam classes Bootstrap (`btn`, `card`, `form-select`, `row`) hoje renderizam sem estilo, porque não há Bootstrap CSS carregado. Telas novas/reescritas devem seguir o padrão Tailwind já usado em `os/show.php` (`bg-white border rounded-2xl`, paleta slate, etc).

## Bug pré-existente a corrigir de passagem

`OrdensServicoController::updateItemStatus()` e a view `os/show.php` já usam os valores de status de item `em_andamento` e `bloqueado`, mas a coluna `man_work_order_items.status` no banco é `ENUM('pendente','em_execucao','concluido','cancelado')` — salvar um desses dois valores quebraria com erro de SQL. Vou alinhar o ENUM do banco aos valores que o código já espera.

## 1. Novo pipeline de status (`man_work_orders.status`)

Novo `ENUM`: `solicitacao`, `aguardando_agendamento`, `em_execucao`, `analise_aprovacao`, `encerrada`, `cancelada`.

Migração de dados existentes (executada antes de estreitar o ENUM):

| status atual | novo status |
|---|---|
| rascunho | solicitacao |
| aprovada | aguardando_agendamento |
| programada | aguardando_agendamento |
| em_execucao | em_execucao |
| aguardando_pecas | em_execucao |
| concluida | analise_aprovacao |
| encerrada | encerrada |
| cancelada | cancelada |

`mudarStatus()`/`changeStatus()` continuam existindo; a lista `allowedStatus` nos dois lugares (controller `store()` e `changeStatus()`) passa a usar o novo conjunto. A regra de bloqueio de encerramento (`motivosBloqueioEncerramento`) não muda de lógica, só passa a ser alcançada a partir de `analise_aprovacao`.

## 2. Visão Kanban por status (nova, em `os/index.php`)

A tela de OS ganha um terceiro modo de visualização: **Kanban | Agenda | Lista** (Agenda e Lista continuam exatamente como estão, incluindo o board por executante com drag-and-drop de agendamento — não é escopo desta mudança).

Kanban:
- 4 colunas fixas: Solicitação, Aguardando/Agendado, Em Execução, Análise e Aprovação.
- 2 abas acima do board: **Em andamento** (status não é `encerrada`/`cancelada`) e **Concluídas** (`encerrada` ou `cancelada`, listadas em uma coluna única de histórico, sem drag).
- Cada card mostra: código, veículo, motivo_abertura (truncado), prioridade, valor total (soma de serviços + mão de obra + peças), "há X dias" desde `aberta_em`.
- Drag-and-drop entre as 4 colunas ativas chama um novo endpoint AJAX `changeStatusDrag` (mesmo padrão de fetch already usado por `assignExecutor`), que só aceita transições dentro do conjunto das 4 colunas (nunca para `encerrada`, que exige o formulário dedicado com odômetro).
- Estilo: Tailwind, consistente com `os/show.php` (não replica cores da referência, usa a paleta slate do app).

## 3. Formulário "Nova OS" (`os/create.php`, reescrito em Tailwind)

Novas colunas em `man_work_orders`:

```sql
ALTER TABLE man_work_orders
  ADD COLUMN medicao_tipo ENUM('odometro','horimetro') NOT NULL DEFAULT 'odometro' AFTER veiculo_id,
  ADD COLUMN disponibilidade ENUM('disponivel','indisponivel') NULL AFTER medicao_tipo,
  ADD COLUMN prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media' AFTER disponibilidade,
  ADD COLUMN motivo_abertura VARCHAR(255) NULL AFTER prioridade,
  ADD COLUMN tipo_fornecedor ENUM('interno','externo') NOT NULL DEFAULT 'externo' AFTER motivo_abertura,
  ADD COLUMN fornecedor VARCHAR(160) NULL AFTER tipo_fornecedor,
  ADD COLUMN responsavel_id INT NULL AFTER fornecedor;
```

Campos do formulário (todos em `store()`/`atualizar()`):
- Status (select, novo pipeline, default `solicitacao`).
- Veículo (select, já existe).
- Medição: toggle Odômetro/Horímetro + campo numérico (`odometro_abertura` reaproveitado para os dois casos, rótulo muda conforme `medicao_tipo`).
- Disponibilidade (select: Disponível/Indisponível).
- Prioridade (select).
- Motivo da abertura (texto, obrigatório) → `motivo_abertura`.
- Nº da OS (texto opcional — se vazio, `gerarCodigo()` gera automaticamente como hoje).
- Data de abertura (datetime-local, default agora) → `aberta_em`.
- Tipo de fornecedor (toggle Externo/Interno) + Fornecedor (texto, obrigatório se Externo).
- Responsável (select de `seg_usuarios`) → `responsavel_id`.
- Pendências em aberto (lista dinâmica de títulos, JS "Adicionar pendência").
- Serviços (lista dinâmica título+valor, JS "Adicionar serviço", com total somado ao vivo).
- Anexos (input múltiplo, aceita PDF/PNG/JPEG).

Validações no controller: veículo válido (já existe), responsável válido (novo `responsavelValido()` no model, mesmo padrão de `executorValido()`), fornecedor obrigatório quando `tipo_fornecedor = externo`, motivo_abertura obrigatório.

## 4. Pendências (nova tabela)

```sql
CREATE TABLE man_work_order_pendencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  filial_id INT NULL,
  work_order_id INT NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  resolvida TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_wo_pendencias (work_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Model: `addPendencia()`, `resolverPendencia()`, `listarPendencias()`. Controller: `addPendencia()`/`resolvePendencia()` (mesmo padrão de `addItem`/`updateItemStatus`), usadas tanto na criação (lista antes de existir OS, processada em `store()` após `criar()`) quanto na tela de detalhe.

## 5. Serviços (reaproveita `man_work_order_items`)

```sql
ALTER TABLE man_work_order_items
  MODIFY COLUMN status ENUM('pendente','em_andamento','concluido','bloqueado','cancelado') NOT NULL DEFAULT 'pendente',
  ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER prioridade;
```

- `addItem()` passa a aceitar `valor`.
- No formulário de criação, o bloco "Serviços" grava linhas em `man_work_order_items` com `titulo` + `valor` (status/prioridade default), depois do `criar()`.
- Na tela de detalhe, a seção "Itens da OS" é renomeada para "Serviços" e passa a mostrar/editar o valor também, mantendo o fluxo de status por item que já existe (necessário para a regra de encerramento).
- Total exibido (criação e detalhe) = soma de `items.valor` + `labor.total` + `parts.total`.

## 6. Anexos na criação

`AnexosModel::salvar()` hoje só aceita imagem (jpeg/png/webp/gif). Adiciono `application/pdf` (extensão `.pdf`) à lista de mimes permitidos, sem mudar o restante do método.

`OrdensServicoController::store()` passa a chamar `$this->anexosModel->salvar('os', $osId, $_FILES['anexos'] ?? [], $userId)` depois de criar a OS (só se houver arquivo). `show()` ganha o mesmo tratamento de POST com upload que `SolicitacoesServicoController::show()` já tem, para permitir anexar depois de criada.

## 7. Lembretes (reaproveita tela de Vencimentos)

Sem mudança de schema. Ajustes em `PlanosPreventivaController::vencimentos()` / `PlanosPreventivaModel::listarVencimentos()` / view `preventiva/vencimentos.php`:
- Renomear rótulo no menu (`layout.php`) de "Vencimentos" para "Lembretes" (mantendo a rota `vencimentos_preventiva` para não quebrar links).
- Adicionar contagem por status nas abas (Todos/Em breve/Atrasados), calculada no model.
- Reformatar a coluna de vencimento para "Há X dias" / "Restam Y km", igual à referência, reaproveitando o padrão de formatação já usado em `os/index.php` para datas.
- Não implementar os estados "Em andamento"/"Fechados" da referência — dependem de vincular OS a plano preventivo, que é uma decisão de schema pendente e já registrada separadamente (fora do escopo desta mudança).

## Fora de escopo (explícito)

- Vincular `man_work_orders` a `man_maintenance_due`/plano preventivo (já é uma decisão pendente registrada à parte).
- Cadastro de fornecedores ou catálogo de serviços como tabelas/telas próprias.
- Qualquer mudança em timeout de sessão (decisão pendente separada, não relacionada a este trabalho).
- Reescrever a Agenda por executante (drag-and-drop existente) — ela continua como está.

## Plano de testes manual (antes de considerar pronto)

1. Criar OS em cada novo status, com e sem pendências/serviços/anexos.
2. Verificar que a soma do "Total" bate com serviços + mão de obra + peças.
3. Arrastar um card no Kanban entre as 4 colunas e confirmar persistência do status.
4. Tentar mover um card do Kanban para "encerrada" (não deve ser um destino de drag).
5. Encerrar uma OS a partir de `analise_aprovacao` com e sem odômetro/itens pendentes (deve bloquear conforme regra existente).
6. Conferir que as 10 OS existentes migraram para o status novo esperado.
7. Abrir a tela de Lembretes e conferir contagem das abas e formatação de "há X dias"/"restam Y km".
8. Rodar `php -l` em todos os arquivos PHP alterados.
