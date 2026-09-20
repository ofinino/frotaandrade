# Redesenho do módulo de Plano de Manutenção (Preventiva)

Data: 2026-09-19
Ambiente: `C:\xampp\htdocs\frotaandrade` (branch `refactor-inicial`), banco não está em produção. `man_maintenance_plans`, `man_maintenance_tasks` e `man_maintenance_due` estão todas com 0 linhas — podem ser derrubadas e recriadas sem migração de dados.
Referência visual: `img_modelos/Imagem Plano Manutencao {1,2,3}.png`, `img_modelos/Imagem cadastro novo plano de manutencao.png`.

## Contexto

O usuário não gostou da implementação atual de "Planos de Preventiva" (`app/Modulos/Manutencao/{Models,Controllers}/PlanosPreventivaModel.php`/`PlanosPreventivaController.php`, views em `app/Modulos/Manutencao/Views/preventiva/`): plano preso a 1 veículo só (campo de texto pro ID do veículo, UX ruim), tarefas com km/dias duplicando os campos do plano, telas em Bootstrap sem CSS carregado (mesmo problema já identificado no módulo de OS). Pediu para apagar e refazer conforme as imagens de referência.

Também achei um bug latente: `PlanosPreventivaModel::detectarOdometroColuna()` procura em `cad_veiculos` por `odometro_atual`, `odometro` ou `km_atual` — nenhuma dessas colunas existe. A coluna real é `nin_km_preventiva`. Isso fazia `processarPreventiva()` nunca conseguir o odômetro atual do veículo, então o cálculo por KM nunca funcionava de verdade. Corrigido nesta reformulação.

Decisões validadas com o usuário:
1. Criar um catálogo de serviços reutilizável (nova tabela + tela simples), em vez de texto livre por tarefa.
2. Pode apagar as tabelas atuais e recriar do zero (sem dado real a preservar).
3. Fora de escopo: biblioteca de "modelos" de plano ("Usar modelo" na referência) — implementar só "Começar do zero".

## 1. Catálogo de serviços (novo)

```sql
CREATE TABLE man_maintenance_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_services_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Tela nova simples: `preventiva/servicos_index.php` — lista com busca + "Novo serviço" (nome apenas, cria inline via POST, sem página separada) + toggle ativo/inativo. Acessível a partir da tela de Planos (link "Gerenciar serviços") e usada para popular o multi-select em "Adicionar serviço".

## 2. Plano de manutenção (schema reescrito)

Dropar `man_maintenance_plans` e recriar:

```sql
DROP TABLE IF EXISTS man_maintenance_due;
DROP TABLE IF EXISTS man_maintenance_tasks;
DROP TABLE IF EXISTS man_maintenance_plans;

CREATE TABLE man_maintenance_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(180) NOT NULL,
    medicao_tipo ENUM('odometro','horimetro') NOT NULL DEFAULT 'odometro',
    association_type ENUM('unidade','veiculos') NOT NULL DEFAULT 'veiculos',
    association_filial_id INT NULL,
    status ENUM('rascunho','ativo','inativo') NOT NULL DEFAULT 'rascunho',
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plans_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_maintenance_plan_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    UNIQUE KEY uq_plan_veiculo (plan_id, veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`association_type = 'unidade'` → o plano vale para todos os veículos de `association_filial_id`. `association_type = 'veiculos'` → vale só para os veículos listados em `man_maintenance_plan_veiculos`.

## 3. Serviços do plano (substitui "tarefas")

```sql
CREATE TABLE man_maintenance_plan_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    plan_id INT NOT NULL,
    service_id INT NOT NULL,
    tipo ENUM('recorrente','unico') NOT NULL DEFAULT 'recorrente',
    intervalo_tempo_valor INT NULL,
    intervalo_tempo_unidade ENUM('dias','meses','anos') NULL,
    alerta_tempo_dias INT NULL,
    intervalo_medicao INT NULL,
    alerta_medicao INT NULL,
    ultima_execucao_em DATETIME NULL,
    ultimo_valor_medicao INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_services_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`intervalo_medicao`/`alerta_medicao` são em KM se `plans.medicao_tipo = 'odometro'`, em horas se `= 'horimetro'`. `intervalo_tempo_unidade` normaliza pra dias no cálculo (`meses` = 30 dias, `anos` = 365 dias — aproximação, mesma usada por qualquer sistema simples de recorrência). Pelo menos um dos dois grupos (tempo OU medição) deve estar preenchido — mesma validação da referência ("Preencha pelo menos uma recorrência"). `tipo = 'unico'` dispensa essa validação (dispara uma vez, sem repetição).

Formulário "Adicionar serviço" aceita selecionar vários serviços do catálogo de uma vez; cada um vira uma linha própria em `man_maintenance_plan_services` com a mesma recorrência informada naquela submissão.

## 4. Vencimentos (Lembretes) — recálculo adaptado

`man_maintenance_due` recriada:

```sql
CREATE TABLE man_maintenance_due (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    plan_id INT NOT NULL,
    plan_service_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    status ENUM('ok','due_soon','overdue') NOT NULL DEFAULT 'ok',
    due_date DATE NULL,
    due_medicao INT NULL,
    last_check_at DATETIME NULL,
    generated_ss_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_due_plan_service_veiculo (plan_service_id, veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`PlanosPreventivaModel::processarPreventiva()` é reescrito para: 1) resolver a lista de veículos de cada plano ativo (via `association_type`), 2) para cada `plan_service`, calcular `due_date`/`due_medicao` por veículo usando `nin_km_preventiva` do veículo como odômetro atual (corrigindo o bug), 3) gravar/atualizar a linha correspondente em `man_maintenance_due`, 4) criar SS automática (`man_service_requests`) quando `overdue`, exatamente como já fazia. A tela de Lembretes (`vencimentos.php`, já ajustada na sessão anterior) consome essa mesma tabela — sem mudança nela além de já usar `tarefa_nome`/`vehicle_plate`, que o `listarVencimentos()` reescrito continua provendo (agora via join com `plan_services`+`services`+veículo por linha de due, já que um plano pode ter vários veículos).

## 5. Telas

- **Lista** (`preventiva/planos_index.php`): tabs Unidades/Veículos (filtro por `association_type`), busca por nome, tabela (Nome, Status chip, nº Serviços, nº Veículos), paginação simples (client-side ou `LIMIT`/`OFFSET` com querystring `page_num`), botão "Novo plano de manutenção" abre o painel lateral de criação.
- **Criar plano** (painel lateral, reaproveitando um `<dialog>`/overlay simples com Tailwind — sem framework de modal novo): Nome, Medição (toggle Odômetro/Horímetro), Associação (select: "Toda a unidade: <filial>" por filial, ou "Veículos específicos" que revela um multi-select de veículos), botão "Criar" → grava plano em `rascunho` e vai para a página de detalhe.
- **Detalhe do plano** (`preventiva/plano_show.php`): nome + status chip, botão "Publicar" (rascunho→ativo, escondido se já ativo/inativo) e um botão simples "Inativar"/"Reativar", abas Serviços (N) / Veículos (N). Aba Serviços vazia mostra estado vazio + "Adicionar serviço"; populada lista cada serviço com sua recorrência formatada e um botão remover. Aba Veículos lista os veículos associados com botão adicionar/remover (só relevante quando `association_type = 'veiculos'`; quando `'unidade'`, mostra todos os veículos da filial, somente leitura).
- **Adicionar serviço** (`preventiva/plano_service_form.php`): multi-select do catálogo (com link "Gerenciar serviços"), toggle Recorrente/Único, bloco "Intervalo de tempo" (valor + unidade + alerta em dias) e bloco "Intervalo de odômetro"/"Intervalo de horímetro" (rótulo conforme `medicao_tipo` do plano) com alerta na mesma unidade, aviso de que ao menos uma recorrência é obrigatória (exceto tipo único).

Tudo em Tailwind, seguindo o padrão já usado em `os/show.php` e nas telas novas do módulo de OS.

## Fora de escopo

- Biblioteca de modelos de plano ("Usar modelo").
- Duplicar plano.
- Qualquer alteração no módulo de OS além do que já foi commitado nesta sessão.

## Plano de testes manual

1. Criar um serviço novo no catálogo.
2. Criar um plano por "Unidade" (toda a filial) e outro por "Veículos específicos" (2+ veículos).
3. Em cada plano, adicionar 2 serviços de uma vez com a mesma recorrência (uma por tempo, outra por odômetro).
4. Publicar um plano (rascunho→ativo) e confirmar que o outro continua rascunho.
5. Rodar a preventiva (`action=run`) e conferir que `man_maintenance_due` foi populada corretamente para os veículos de cada plano.
6. Conferir que um veículo com `nin_km_preventiva` alto o suficiente aparece como `overdue` e gera uma SS automática.
7. Abrir a tela de Lembretes e conferir que os nomes de veículo/serviço aparecem certos.
8. Inativar um plano ativo e confirmar que ele some da lista de planos considerados por `processarPreventiva()`.
9. `php -l` em todos os arquivos alterados.
