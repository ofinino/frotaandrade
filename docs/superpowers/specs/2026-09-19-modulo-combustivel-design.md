# Módulo de Combustível (Abastecimentos + Tanques)

Data: 2026-09-19
Ambiente: `C:\xampp\htdocs\frotaandrade` (branch `refactor-inicial`), banco de dev compartilhado com a produção real (`app.viacaoandrade.com.br` roda a branch `main`, ainda desatualizada — o usuário fará o deploy por conta própria depois). Nenhuma tabela nova aqui colide com nada existente.
Referência: dois vídeos em `img_modelos/` (`Abastecimento - Consulta e lançamento de gastos de combustível.mp4`, `Abastecimento - Consulta e lançamento de tanques internos.mp4`), analisados via extração de frames (não há player de vídeo disponível nas minhas ferramentas — usei OpenCV via um venv Python já existente no projeto para tirar screenshots a cada 2s).

## O que os vídeos mostram

**Vídeo 1 (Abastecimentos):** tela "Abastecimentos" com tabela (Data, Veículo, Quantidade, Medição, Fornecedor, Criado por, Valor do litro, Custo total, Medida percorrida, Autonomia média, Anexo, Ações), busca, colunas configuráveis, filtro por período/veículo, resumo no rodapé (total de abastecimentos, custo total, preço médio por litro). Painel "Novo Abastecimento": toggle Comercial/Interno, veículo, data/hora, fornecedor (select com opção de cadastrar na hora), quantidade (L), odômetro (km), tipo de combustível, custo (R$), "tanque cheio", observações, foto da nota fiscal, foto do odômetro, aviso "Insira um odômetro válido atentando-se aos valores permitidos". Ao salvar, a lista recalcula "medida percorrida" (diferença de odômetro desde o abastecimento anterior do mesmo veículo) e "autonomia média" (km/L).

**Vídeo 2 (Meus Tanques):** grade de cards de tanque (nome, estoque atual/capacidade máxima, capacidade máxima), "Adicionar novo tanque" (nome, capacidade máxima, estoque inicial), editar/excluir por tanque. "Compra de combustível": nº da nota, data, valor pago, quantidade, escolha do tanque (rádio) — soma no estoque do tanque. Abaixo da grade, "Histórico de compra" com filtro por período/tanque e resumo no rodapé.

O modo "Interno" do abastecimento (puxar direto de um tanque em vez de um fornecedor externo) não aparece sendo demonstrado nos vídeos, só o toggle existe — o design abaixo assume o comportamento óbvio (troca Fornecedor por Tanque, desconta do estoque) e foi aprovado pelo usuário.

## Achado durante a investigação

`cad_veiculos.nin_km_preventiva` já existe e já é lido por `PlanosPreventivaModel::processarPreventiva()` como "odômetro atual do veículo" (bug corrigido na sessão anterior), mas **nada no sistema grava nesse campo** — a preventiva por KM nunca teve dado real pra funcionar. O módulo de combustível passa a ser a fonte de verdade desse campo: todo abastecimento salvo atualiza `nin_km_preventiva` para o maior odômetro já registrado daquele veículo.

## Schema (tudo novo, sem conflito)

```sql
CREATE TABLE cad_fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fornecedores_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_tanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    capacidade_maxima DECIMAL(10,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tanks_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_tank_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    tank_id INT NOT NULL,
    numero_nota VARCHAR(60) NULL,
    data DATE NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantidade DECIMAL(10,2) NOT NULL,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tank_purchases_tank (tank_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE man_fuel_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NULL,
    veiculo_id INT NOT NULL,
    tipo ENUM('comercial','interno') NOT NULL DEFAULT 'comercial',
    fornecedor_id INT NULL,
    tank_id INT NULL,
    data_hora DATETIME NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    odometro DECIMAL(10,2) NOT NULL,
    combustivel_tipo ENUM('alcool','arla32','diesel','diesel_s10','gasolina','gasolina_aditivada') NOT NULL DEFAULT 'diesel',
    custo DECIMAL(10,2) NULL,
    valor_litro DECIMAL(10,4) NULL,
    tanque_cheio TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fuel_records_veiculo (veiculo_id, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Anexos (nota fiscal + foto do odômetro) reaproveitam a tabela genérica `man_attachments` já usada por OS/SS, com `owner_type = 'abastecimento'` — sem tabela nova. `AnexosModel::salvar()` ganha um novo subdiretório `abastecimento`.

## Regras de negócio

- **Comercial**: exige `fornecedor_id` (ou nome novo criado na hora), pede `custo`; `valor_litro = custo / quantidade`.
- **Interno**: exige `tank_id`; sem campo de custo (já pago na compra do tanque); bloqueia se `quantidade > tank.estoque_atual`; ao salvar, decrementa o estoque do tanque numa transação. Editar/excluir um registro interno reverte o delta antigo antes de aplicar o novo (ou soma de volta ao excluir).
- **Odômetro**: deve ser `>=` o maior odômetro já registrado para aquele veículo (em `man_fuel_records` ou, na ausência de registros, `cad_veiculos.nin_km_preventiva`). Caso contrário, bloqueia com a mensagem do vídeo ("Insira um odômetro válido...").
- **Medida percorrida / autonomia média** (list e não gravadas): para cada registro, busca o registro anterior do mesmo veículo (por `data_hora`, desempate por `id`); `medida_percorrida = odometro - anterior.odometro` (vazio se não há anterior); `autonomia_media = medida_percorrida / quantidade` (vazio se `medida_percorrida` não existir ou for ≤ 0).
- Após criar/editar/excluir qualquer abastecimento, `cad_veiculos.nin_km_preventiva` é recalculado como o maior `odometro` entre os registros restantes daquele veículo (mantém consistência sem duplicar a fonte de verdade).
- Tipo de combustível pré-selecionado a partir de `cad_veiculos.txt_tipo_combustivel` quando reconhecido, com fallback pro combustível default do formulário.

## Telas

- **Abastecimentos** (`page=abastecimentos`): lista Tailwind com busca, filtro de período/veículo, tabela com as colunas do vídeo, resumo no rodapé, botão "Novo Abastecimento" (painel lateral).
- **Novo/Editar Abastecimento**: painel lateral com o toggle Comercial/Interno e os campos descritos acima.
- **Meus Tanques** (`page=meus_tanques`): grade de cards + "Adicionar novo tanque" + "Compra de combustível" (painel lateral) + tabela "Histórico de compra" com filtro e resumo.
- Menu: novo grupo "Combustível" na sidebar com os itens Abastecimentos e Meus tanques, permissões `combustivel.view`/`combustivel.manage` (mesmo padrão de `os.view`/`os.manage`; usuário de teste já é admin, então nada a semear pra testar).

## Fora de escopo

- Relatórios/gráficos de consumo.
- Exportar para Excel/CSV (o vídeo mostra "Exportar"/"Baixar tabela", mas não é essencial pro módulo funcionar; fica pra depois se pedido).
- "Grupos" de veículos nos filtros (conceito que não existe no sistema).
- Multi-fornecedor/multi-tanque por linha de abastecimento (cada abastecimento é de um único fornecedor OU tanque).

## Plano de testes manual

1. Criar um tanque, comprar combustível pra ele, conferir que o estoque soma certo.
2. Criar um abastecimento Comercial com fornecedor novo (criado na hora) e conferir valor do litro calculado.
3. Criar um abastecimento Interno puxando do tanque acima; conferir que o estoque do tanque desconta.
4. Tentar abastecimento Interno com quantidade maior que o estoque do tanque — deve bloquear.
5. Criar um segundo abastecimento do mesmo veículo com odômetro maior; conferir "medida percorrida"/"autonomia média" calculados.
6. Tentar um odômetro menor que o último — deve bloquear.
7. Conferir que `cad_veiculos.nin_km_preventiva` foi atualizado após os abastecimentos.
8. Excluir um abastecimento Interno e conferir que o estoque do tanque volta.
9. Anexar nota fiscal (PDF) e foto do odômetro (imagem) num abastecimento; conferir que aparecem certos na lista/detalhe.
10. `php -l` em todos os arquivos alterados/criados.
