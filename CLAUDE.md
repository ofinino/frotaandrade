# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## O que é

Sistema de gestão de frota ("frotaandrade"): checklists de veículos, ordens de serviço, planos de manutenção preventiva, cadastros (veículos/pessoas/empresas) e controle de acesso por papéis. PHP puro (sem framework), MySQL via PDO, sem Composer/Node — tudo escrito à mão.

## Rodando o projeto

Não há build step, gerenciador de dependências ou suíte de testes automatizada (sem Composer, sem npm, sem PHPUnit). O projeto roda direto com o servidor embutido do PHP:

```
php -S localhost:8000
```

Requer um `config.php` na raiz (não versionado — está no `.gitignore` e **não existe no repo**; precisa ser criado manualmente na máquina/servidor) retornando um array com `db_host`, `db_port`, `db_name`, `db_user`, `db_pass`, `uploads_path`, `base_url` etc. As credenciais de referência ficam em `.env`, mas `.env` não é lido automaticamente em runtime — `bootstrap.php` exige `config.php` diretamente (`require __DIR__ . '/config.php'`).

Job de cron para preventivas:
```
php bin/run_preventiva.php [empresa_id]
```
(executa `PlanosPreventivaModel::processarPreventiva`, gera vencimentos e solicitações de serviço automaticamente.)

Backup de banco é feito em runtime pelo módulo Segurança via `bin/mysqldump.exe` (Windows), chamado com `exec()` em `BackupController::runBackup`.

## Arquitetura

Fluxo de request: `index.php` → `bootstrap.php` (sessão, config, autoload, `Core.php`) → `App\Core\Router::dispatch()` → controller → `App\Core\View::render()` (que envolve a view com `layout.php`).

- **Roteamento em duas camadas**: `index.php` mapeia "páginas amigáveis" (`?page=vehicles`, `?page=os`, etc., ver o array `$routes` em `index.php`) para o formato interno `?mod=&ctrl=&action=`. O roteador real, `App\Core\Router`, resolve isso via reflexão de classe/método: `\App\Modulos\{Mod}\Controllers\{Ctrl}Controller->{action}()`, sem tabela de rotas nem verbos HTTP — é convenção de nome, não config declarativa.
- **Módulos = bounded contexts**, cada um com `Controllers/`, `Models/`, `Views/` sob `app/Modulos/{Nome}/`:
  - `Cadastros`: Dashboard, Veículos, Pessoas, Grupos, Empresa, Filiais.
  - `Manutencao`: Checklists (templates + versionamento), Execuções (preenchimento de checklist por veículo, upload de mídia), Ordens de Serviço, Solicitações de Serviço, Planos de Preventiva, Relatórios, Vídeos, Media (serve arquivos de upload).
  - `Seguranca`: Usuários, Access (papéis/permissões), Backup, Cleanup.
- **`app/Core/Core.php`** não é uma classe — é um arquivo de funções globais soltas que todo o app depende: conexão PDO (`db()`), sessão/autenticação (`current_user`, `login_user`, `require_login`), autorização (`has_permission`, `require_permission`, `require_role`), multi-empresa/multi-filial (`current_company_id`, `current_branch_ids`), sanitização de output (`sanitize()` = wrapper de `htmlspecialchars`), uploads (`upload_base_dir`, `ensure_upload_dir`) e helpers de URL/assets.
- **Autoload PSR-4 caseiro** em `app/Core/autoload.php` para o namespace `App\` — não é Composer.
- **Multi-tenant por empresa/filial**: a maioria dos Models recebe `($db, $empresaId, $filialId, $filiaisIds)` no construtor e filtra as queries por essas colunas — ao criar/alterar queries, sempre respeitar esse escopo (ver qualquer Model em `Manutencao`, ex. `PlanosPreventivaModel`, `OrdensServicoModel`).
- **Sistema de permissões com 3 camadas de fallback** em `Core.php::load_user_permissions()` / `has_permission()`: tenta o esquema novo (`seg_usuario_papel` + `seg_papel_permissoes`), cai para um esquema legado (`seg_usuario_roles` + `seg_role_permissao` + `seg_permissoes`) se a tabela nova não retornar nada, e por fim mapeia pelo campo `role` do usuário via `seg_papeis`. Há também um dicionário de aliases (`checklist.ver` → `checks.view`, etc.) para compatibilizar chaves de permissão antigas com as novas — dívida técnica de uma migração de schema incompleta; não remover sem confirmar que não há usuários no esquema antigo.
- **Frontend**: PHP puro nas views (`include`, sem template engine). `index.php` (site institucional) usa Bootstrap CSS local; as telas internas (login, dashboard, módulos) usam Tailwind via CDN — duas abordagens coexistindo, não unificar sem necessidade.

## Convenções de código

- Acesso a banco **sempre via PDO com prepared statements** (`db()->prepare(...)->execute([...])`) — nunca interpolar `$_GET`/`$_POST` direto em SQL.
- Output em views sempre passa por `sanitize()` (`<?= sanitize($var) ?>`), não `htmlspecialchars` cru nem output direto.
- Senhas: `password_hash()` / `password_verify()` (bcrypt). Nunca armazenar em texto puro.
- Nomes de tabela em `snake_case` prefixados por módulo: `cad_*` (Cadastros), `man_*` (Manutenção), `seg_*` (Segurança) — ver lista de tabelas abaixo.
- Controllers chamam `require_login()` / `require_permission()` / `require_role()` no início de cada ação sensível — seguir esse padrão em ações novas.
- Não há `session_regenerate_id()` nem tokens CSRF no código atual — ao mexer em autenticação/forms sensíveis, considerar adicionar em vez de assumir que já existe proteção.

## Principais tabelas (MySQL, sem arquivo de schema no repo — inferidas do código)

**Cadastros (`cad_*`)**: `cad_empresas`, `cad_filiais`, `cad_grupos`, `cad_veiculos`, `cad_pessoas`

**Segurança (`seg_*`)**: `seg_usuarios`, `seg_papeis`, `seg_papel_permissoes`, `seg_usuario_papel`, `seg_usuario_filiais`, `seg_backup_config`
- Esquema legado (fallback, pode não existir em bancos novos): `seg_usuario_roles`, `seg_role_permissao`, `seg_permissoes`

**Manutenção (`man_*`)**:
- Checklists: `man_checklists`, `man_checklist_itens`, `man_checklist_versoes`, `man_checklist_versao_itens`, `man_checklist_revision_logs`
- Execução: `man_checklist_execucoes`, `man_checklist_respostas`, `man_checklist_midias`, `man_attachments`
- Ordens de serviço: `man_work_orders`, `man_work_order_items`, `man_work_order_labor`, `man_work_order_parts`, `man_work_order_schedule`
- Solicitações de serviço: `man_service_requests`, `man_service_request_work_order`
- Preventiva: `man_maintenance_plans`, `man_maintenance_tasks`, `man_maintenance_due`
- Auditoria: `man_audit_log`

Vários Models consultam `INFORMATION_SCHEMA` em runtime para checar existência de colunas antes de montar queries (ver `OrdensServicoModel`, `PlanosPreventivaModel`, `SolicitacoesServicoModel`, `ExecucoesModel`) — sinal de que o schema evoluiu incrementalmente e nem todo ambiente tem as mesmas colunas; não assumir que uma coluna existe sem checar o Model correspondente.

## Design visual

O módulo de Ordens de Serviço (`app/Modulos/Manutencao/Views/os/*.php`) recebeu um passe de design (skill `frontend-design`) que deve ser usado como padrão pra qualquer tela nova ou redesenho — não é preciso pedir de novo, aplicar direto.

- **Sistema de tokens**: definido em `layout.php`, escopado pela classe `.os-redesign` (evita vazar pro resto do app enquanto nem tudo foi migrado). Cores: `--os-paper` (fundo cinza-aço frio, não creme), `--os-surface` (branco), `--os-ink`/`--os-ink-muted` (texto), `--os-steel`/`--os-steel-strong` (ação primária, azul-aço — não preto genérico nem roxo de SaaS), `--os-signal` (laranja de sinalização, só pra acentos/detalhes, nunca em botão), `--os-line` (bordas finas).
- **Tipografia**: "Archivo" pra título/UI, "IBM Plex Mono" (classe `.os-mono`) só pra dado técnico/serial — código de OS, placa, odômetro, valores em tabela. Nunca usar mono pra rótulo comum.
- **Fontes self-hosted**: arquivos em `assets/fonts/` (`fonts.css` + `.woff2`), carregados por caminho relativo simples (`assets/fonts/fonts.css`) — **nunca** via CDN externa (Google Fonts, etc.) nem qualquer recurso pago. Se precisar de peso/família nova, baixar os `.woff2` (subset `latin`+`latin-ext` cobre português) e colocar em `assets/fonts/`, igual já foi feito.
- **Ao estender pra outro módulo**: reaproveitar a mesma classe `.os-redesign` (ou renomear pra algo mais genérico tipo `.app-redesign` se for aplicar no projeto inteiro — meio caminho já andado, não recriar um sistema de cores do zero) e os overrides já existentes em `layout.php` (`.text-slate-900`, `.bg-slate-900`, `.rounded-2xl`, `.shadow-sm`, etc.) em vez de reescrever cada view do zero.
- Ao aplicar num módulo inteiro, sempre testar via `php -l` + verificação ao vivo (curl com sessão logada) antes de considerar pronto — view PHP quebrada só aparece em runtime, não em lint.

## Pontos de atenção (segurança/dívida técnica)

- `BackupController::runBackup` monta um comando de shell para o `mysqldump.exe`; o caminho do binário e o arquivo de destino vêm de input do formulário (restrito a admin) e não passam por `escapeshellarg()` — só os parâmetros de conexão são escapados. Validar/escapar antes de expandir esse recurso.
- CSRF: todo form usa `csrf_field()`/`csrf_verify()`, checado automaticamente em POST por `require_csrf()` dentro de `App\Core\Router::dispatch()`. `login.php` chama `session_regenerate_id(true)` após autenticar. (Nota desatualizada removida em 2026-09-20 — isso já tinha sido endurecido antes desta sessão.)
- Uploads (`ExecucoesController`, `AnexosModel`) usam `move_uploaded_file()`; confirmar validação de tipo/extensão antes de expandir tipos de arquivo aceitos.
- `config.php` é obrigatório e não versionado — qualquer ambiente novo (incluindo CI, se vier a existir) precisa desse arquivo antes de rodar qualquer coisa.
- ~~`asset_url()` gerava caminho absoluto da raiz do domínio quando `config.php` não tinha `base_url`~~ — **corrigido em 2026-09-20**: quando `base_url` não está configurado, `asset_url()` agora detecta o caminho real via `SCRIPT_NAME` (`detect_base_path()`), funcionando tanto em subpasta (`/frotaandrade/`) quanto na raiz do domínio sem precisar configurar nada por ambiente.
