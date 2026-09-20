# Redesenho do Abastecimento + Cadastro de Tipos de Combustível

Data: 2026-09-20. Continuação do módulo de Combustível já implementado nesta sessão.
Referência: `img_modelos/Abastecimento novo modelo.mp4` (frames extraídos com o mesmo método OpenCV das outras sessões).
Escopo confirmado com o usuário: refazer o abastecimento conforme o vídeo, **sem** Motorista e **sem** Centro de Custo (fica pra depois). Adicionar cadastro de Tipo de Combustível (não existia — era um ENUM fixo).

## 1. Cadastro de Tipo de Combustível (novo)

Hoje `man_fuel_records.combustivel_tipo` é um ENUM fixo (`alcool,arla32,diesel,diesel_s10,gasolina,gasolina_aditivada`). Só há 4 registros reais, todos `diesel` — migração simples.

- Nova tabela `man_fuel_types` (id, empresa_id, filial_id, nome, ativo, created_at, updated_at) — mesmo padrão de `man_maintenance_services`.
- Seed com os 6 tipos atuais por empresa existente em `cad_empresas`.
- `man_fuel_records` ganha `combustivel_tipo_id INT` (FK), substituindo a coluna ENUM (a coluna antiga é removida depois de migrar os dados).
- Novo `FuelTypesModel`/`FuelTypesController`/view `combustivel/tipos/index.php` — cópia do padrão já usado em `ManutencaoServicosModel` (listar, criar, toggle ativo). Rota `combustivel_tipos`, link "Gerenciar tipos de combustível" no formulário de abastecimento (igual ao link "Gerenciar serviços" que já existe no plano de manutenção).
- `AbastecimentosController`/`FuelRecordsModel` passam a usar o catálogo (join pelo id) em vez do array fixo de labels.

## 2. Formulário "Novo Abastecimento" (redesenho)

Mantém tudo que já existe (toggle Comercial/Interno, Fornecedor com criação inline, Tanque, Odômetro, Quantidade, Combustível agora do catálogo, Custo só no Comercial, Tanque cheio, Observações, Anexos) e adiciona:

- **Toggle "Atualizar odômetro"** (novo, desligado por padrão): controla se *aquele* abastecimento deve atualizar `cad_veiculos.nin_km_preventiva`. Antes eu sincronizava automaticamente sempre que a leitura era a mais alta; agora só sincroniza quando o usuário liga esse toggle nesse abastecimento especificamente. A validação de odômetro mínimo continua olhando todos os registros (isso é consistência interna, não depende do toggle).
- **Tela de sucesso** depois de salvar: resumo (data, veículo, combustível, quantidade, odômetro, custo) + botões "Concluir" (volta pra lista) / "Novo Abastecimento" (abre o formulário limpo de novo).
- Sem Motorista, sem Centro de Custo, sem o "passo 2" do vídeo (que era só sobre centro de custo) — fica um formulário de passo único.

Mudança de schema: `man_fuel_records` ganha `atualizar_odometro TINYINT(1) NOT NULL DEFAULT 0`. `sincronizarOdometroVeiculo()` passa a considerar só registros com `atualizar_odometro = 1` ao calcular o odômetro atual do veículo (se nenhum registro tiver o toggle ligado, não mexe no campo do veículo).

## 3. Lista de Abastecimentos (redesenho)

- 4 cards de resumo no topo: Total de abastecimentos, Custo total, Preço médio por litro, Quantidade de litros (já calculo os 3 primeiros; adiciono quantidade total).
- Filtro: "Mês atual" (presets: mês atual, mês anterior, personalizado com as datas que já tenho) + veículo + botão "Filtros" (abre o range picker que já existe).
- Abas **Todos** / **Inconsistentes** (com contagem): marco como inconsistente um abastecimento com autonomia média calculada abaixo de 1 km/L (valor implausível, sinal de erro de digitação de odômetro ou quantidade) — regra simples, sem inventar heurística mais complexa.
- Colunas: Veículo, Data, Medição (odômetro), **Tipo** (nome do combustível do catálogo, chip colorido — cor por hash do nome, não fixa por tipo, já que agora é cadastrável), Fornecedor/Tanque, Medida percorrida, Quantidade, Autonomia média (com aviso visual quando inconsistente), Custo (com R$/L embaixo), Anexo, Ações.
- Paginação simples (Itens por página + Mostrando X de Y + Anterior/Próxima) — hoje a lista não pagina.

Fora do escopo (confirmado): Motorista, Centro de Custo, "Colunas" configurável (manter todas as colunas fixas, sem toggle), "Exportar".

## Arquivos

| Arquivo | Ação |
|---|---|
| `db/migrations/2026-09-20-tipos-combustivel-e-abastecimento-v2.sql` | novo (gitignored) |
| `app/Modulos/Combustivel/Models/FuelTypesModel.php` | novo |
| `app/Modulos/Combustivel/Controllers/FuelTypesController.php` | novo |
| `app/Modulos/Combustivel/Views/tipos/index.php` | novo |
| `app/Modulos/Combustivel/Models/FuelRecordsModel.php` | reescreve `listar()`/`criar()`, adiciona paginação e cálculo de inconsistência |
| `app/Modulos/Combustivel/Controllers/AbastecimentosController.php` | usa catálogo de tipos, paginação, filtros, tela de sucesso |
| `app/Modulos/Combustivel/Views/abastecimentos/index.php` | redesenho completo |
| `app/Modulos/Combustivel/Views/abastecimentos/form.php` | toggle atualizar odômetro, combustível do catálogo, tela de sucesso |
| `index.php`, `layout.php` | rota e link `combustivel_tipos` |

## Testes manuais

1. Cadastrar um tipo de combustível novo e usá-lo num abastecimento.
2. Criar abastecimento com "Atualizar odômetro" desligado — conferir que `nin_km_preventiva` NÃO muda.
3. Criar outro com o toggle ligado — conferir que muda.
4. Criar um abastecimento com autonomia abaixo de 1 km/L — conferir que aparece na aba "Inconsistentes".
5. Conferir os 4 cards de resumo e a paginação com mais de uma página de itens.
6. `php -l` em todos os arquivos alterados.
