<?php
require_once __DIR__ . '/bootstrap.php';
function render_header(string $title = 'Painel'): void
{
    $user = current_user();
    $appName = $GLOBALS['config']['app_name'] ?? 'Checklist';
    $company = current_company();
    $currentPage = $_GET['page'] ?? 'dashboard';

    $navItems = [];
    if ($user) {
        if ($user['role'] === 'executante') {
            if (has_permission('checks.view')) {
                $navItems[] = ['page' => 'checks', 'label' => 'Minhas execuÃ§Ãµes', 'href' => 'index.php?page=checks', 'perm' => true, 'icon' => 'clipboard'];
                $navItems[] = ['page' => 'videos', 'label' => 'VÃ­deos', 'href' => 'index.php?page=videos', 'perm' => true, 'icon' => 'template'];
            }
        } else {
            $navItems[] = ['page' => 'dashboard', 'label' => 'Home', 'href' => 'index.php', 'perm' => true, 'icon' => 'home'];
            if (has_permission('checks.view')) {
                $navItems[] = ['page' => 'checks', 'label' => 'ExecuÃ§Ãµes', 'href' => 'index.php?page=checks', 'perm' => true, 'icon' => 'list'];
                $navItems[] = ['page' => 'videos', 'label' => 'VÃ­deos', 'href' => 'index.php?page=videos', 'perm' => true, 'icon' => 'template'];
            }
            if (has_permission('templates.view')) {
                $navItems[] = ['page' => 'templates', 'label' => 'Modelos', 'href' => 'index.php?page=templates', 'perm' => true, 'icon' => 'template'];
                $navItems[] = ['page' => 'revision_logs', 'label' => 'RevisÃµes', 'href' => 'index.php?page=revision_logs', 'perm' => true, 'icon' => 'history'];
                $navItems[] = ['page' => 'groups', 'label' => 'Grupos', 'href' => 'index.php?page=groups', 'perm' => true, 'icon' => 'folder'];
            }
            if (has_permission('vehicles.view')) {
                $navItems[] = ['page' => 'vehicles', 'label' => 'VeÃ­culos', 'href' => 'index.php?page=vehicles', 'perm' => true, 'icon' => 'truck'];
            }
            if (has_permission('people.view')) {
                $navItems[] = ['page' => 'people', 'label' => 'Pessoas', 'href' => 'index.php?page=people', 'perm' => true, 'icon' => 'users'];
            }
            if (has_permission('users.view')) {
                $navItems[] = ['page' => 'users', 'label' => 'UsuÃ¡rios', 'href' => 'index.php?page=users', 'perm' => true, 'icon' => 'user'];
            }
            if (is_admin()) {
                $navItems[] = ['page' => 'company', 'label' => 'Empresa', 'href' => 'index.php?page=company', 'perm' => true, 'icon' => 'building'];
                $navItems[] = ['page' => 'branches', 'label' => 'Filiais', 'href' => 'index.php?page=branches', 'perm' => true, 'icon' => 'flag'];
                $navItems[] = ['page' => 'access', 'label' => 'Acessos', 'href' => 'index.php?page=access', 'perm' => true, 'icon' => 'shield'];
                $navItems[] = ['page' => 'backup', 'label' => 'Backup DB', 'href' => 'index.php?page=backup', 'perm' => true, 'icon' => 'folder'];
            }
        }
    }

    $icon = function (string $name, string $classes = 'h-5 w-5') {
        $map = [
            'home' => 'M3 9.75 12 3l9 6.75V21H3V9.75z',
            'list' => 'M6 6h12v2H6V6zm0 5h12v2H6v-2zm0 5h12v2H6v-2z',
            'template' => 'M4 5h16v14H4V5zm2 2v10h12V7H6z',
            'history' => 'M5 13a7 7 0 1 1 2 4.95V18h-2v4h4v-2H8.83A9 9 0 1 0 5 13zm6-4v5l4 2',
            'folder' => 'M4 6h5l2 2h9v12H4V6z',
            'truck' => 'M3 7h13v8h3v4h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3V7zm2 2v6h9V9H5z',
            'users' => 'M5 8a3 3 0 1 1 6 0A3 3 0 0 1 5 8zm8 1a2.5 2.5 0 1 1 5 0a2.5 2.5 0 0 1-5 0zM3 17a4 4 0 0 1 8 0v2H3v-2zm10.5 2v-1a3.5 3.5 0 0 1 5.94-2.47L21 16.5V19h-7.5z',
            'user' => 'M12 12a4 4 0 1 1 0-8a4 4 0 0 1 0 8zm0 2c4 0 6 2 6 4v2H6v-2c0-2 2-4 6-4z',
            'building' => 'M4 4h16v16H4V4zm2 2v12h12V6H6zm2 2h2v2H8V8zm0 4h2v2H8v-2zm0 4h2v2H8v-2zm4-8h2v2h-2V8zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2z',
            'flag' => 'M5 4h9l-1 4h6l-1 10H5V4zm2 2v10h10.17l.6-6H11l1-4H7z',
            'shield' => 'M12 3l7 4v6c0 4.5-3 7.74-7 8.99C8 20.74 5 17.5 5 13V7l7-4zm0 3.3L7 8.9V13c0 3.06 1.86 5.55 5 6.7c3.14-1.15 5-3.64 5-6.7V8.9l-5-2.6z',
            'logout' => 'M14 4v2h-4v12h4v2H8V4h6zm2.59 5.59L13.17 13l3.42 3.41L15 18l-5-5l5-5l1.41 1.59z',
        ];
        $path = $map[$name] ?? $map['home'];
        return '<svg class="' . $classes . '" fill="currentColor" viewBox="0 0 24 24"><path d="' . $path . '"/></svg>';
    };
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <script>
            (function() {
                try {
                    const root = document.documentElement;
                    const sidebarTheme = localStorage.getItem('sidebarTheme');
                    const pageTheme = localStorage.getItem('pageTheme');
                    const isSidebarLight = sidebarTheme === 'light';
                    const isPageDark = pageTheme === 'dark';
                    root.classList.toggle('sidebar-light', isSidebarLight);
                    root.setAttribute('data-sidebar-theme', isSidebarLight ? 'light' : 'dark');
                    root.setAttribute('data-page-theme', isPageDark ? 'dark' : 'light');
                } catch (e) {
                    /* ignore */
                }
            })();
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            :root {
                --sidebar-w: 14rem;
                --accent: #4d8dff;
                --accent-strong: #2d6ce0;
                --sidebar-bg: #2f3338;
                --sidebar-bg-strong: #26292d;
                --sidebar-divider: rgba(255,255,255,0.08);
                --sidebar-text: #e8edf5;
                --sidebar-muted: #b9c2d0;
                --sidebar-highlight: #f9fbff;
                --nav-accent: var(--accent);
            }
            html.sidebar-light {
                --sidebar-bg: #ffffff;
                --sidebar-bg-strong: #f5f7fb;
                --sidebar-divider: rgba(0,0,0,0.06);
                --sidebar-text: #1f2937;
                --sidebar-muted: #4b5563;
                --sidebar-highlight: #1f2937;
            }
            html { scrollbar-gutter: stable; }
            body { margin: 0; }

            /* Novo menu lateral */
            #app-shell { gap: 0; }
            #sidebar.sidebar-v2 {
                position: fixed;
                inset: 0 auto 0 0;
                width: var(--sidebar-w);
                background: linear-gradient(180deg, var(--sidebar-bg-strong) 0%, var(--sidebar-bg) 40%, var(--sidebar-bg-strong) 100%);
                color: var(--sidebar-text);
                display: flex;
                flex-direction: column;
                z-index: 50;
                box-shadow: 8px 0 24px rgba(0,0,0,0.28);
                transition: transform 200ms ease;
            }
            .sidebar-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1rem 1.1rem 0.5rem;
                gap: 0.6rem;
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            .brand-logo {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                object-fit: contain;
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.12);
                padding: 6px;
            }
            .brand-mark {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                background: rgba(255,255,255,0.12);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 800;
                color: var(--sidebar-highlight);
                border: 1px solid rgba(255,255,255,0.12);
            }
            .brand-text { display: flex; flex-direction: column; line-height: 1.1; }
            .brand-name { font-size: 16px; color: var(--sidebar-highlight); }
            .brand-sub { font-size: 12px; color: var(--sidebar-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.02em; }
            .sidebar-close {
                background: none;
                border: 0;
                color: var(--sidebar-text);
                display: none;
                padding: 6px;
            }

            .sidebar-scroll {
                flex: 1;
                overflow-y: auto;
                padding: 0.5rem 0.5rem 0.75rem;
            }
            .menu-root { display: flex; flex-direction: column; gap: 0.25rem; }
            .menu-group {
                border-radius: 12px;
                overflow: hidden;
                border: 1px solid var(--sidebar-divider);
                background: rgba(255,255,255,0.02);
            }
            html.sidebar-light .menu-group { background: #ffffff; border-color: rgba(0,0,0,0.06); }
            .menu-group + .menu-group { margin-top: 0.25rem; }
            .menu-group-trigger {
                width: 100%;
                background: transparent;
                border: 0;
                color: var(--sidebar-text);
                padding: 0.85rem 0.95rem;
                display: flex;
                align-items: center;
                gap: 0.6rem;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
            }
            .menu-group-trigger .menu-icon svg { width: 22px; height: 22px; }
            .menu-chevron { margin-left: auto; transition: transform 160ms ease; color: var(--sidebar-muted); }
            .menu-group.open .menu-chevron { transform: rotate(180deg); }
            .menu-group.open .menu-group-trigger {
                background: rgba(0,0,0,0.08);
                border-bottom: 1px solid var(--sidebar-divider);
            }
            .menu-items { display: none; padding: 0.2rem 0; background: rgba(0,0,0,0.12); }
            .menu-group.open .menu-items { display: block; }
            html.sidebar-light .menu-items { background: #f7f9fc; }
            .menu-link {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                padding: 0.55rem 1.5rem;
                color: var(--sidebar-muted);
                font-size: 14px;
                text-decoration: none;
                transition: background-color 160ms ease, color 160ms ease;
            }
            .menu-link:hover { background: rgba(255,255,255,0.08); color: var(--sidebar-text); }
            html.sidebar-light .menu-link:hover { background: rgba(0,0,0,0.04); }
            .menu-link[data-active="true"] {
                background: rgba(77, 141, 255, 0.18);
                color: var(--sidebar-highlight);
                font-weight: 700;
                border-left: 3px solid var(--accent);
            }
            html.sidebar-light .menu-link[data-active="true"] {
                background: rgba(77, 141, 255, 0.22);
                color: var(--sidebar-text);
            }
            .menu-dot {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: var(--sidebar-muted);
                opacity: 0.65;
            }
            .menu-link[data-active="true"] .menu-dot {
                background: var(--accent);
                opacity: 1;
                box-shadow: 0 0 0 4px rgba(77, 141, 255, 0.12);
            }

            .sidebar-footer {
                border-top: 1px solid var(--sidebar-divider);
                padding: 0.8rem 1rem 1rem;
                color: var(--sidebar-muted);
                display: grid;
                gap: 0.65rem;
            }
            .theme-toggle {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                background: transparent;
                border: 1px solid var(--sidebar-divider);
                color: var(--sidebar-highlight);
                padding: 0.15rem 0.35rem;
                border-radius: 10px;
                cursor: pointer;
            }
            html.sidebar-light .theme-toggle { background: rgba(0,0,0,0.04); border-color: rgba(0,0,0,0.08); color: var(--sidebar-text); }
            .theme-switch {
                width: 32px;
                height: 16px;
                border-radius: 999px;
                background: rgba(77,141,255,0.25);
                position: relative;
                border: 1px solid var(--sidebar-divider);
            }
            .theme-knob {
                position: absolute;
                top: 1.5px;
                left: 2px;
                width: 12px;
                height: 12px;
                border-radius: 999px;
                background: #fff;
                transition: transform 150ms ease;
            }
            .logout-link { color: var(--sidebar-text); border-radius: 10px; border: 1px solid transparent; }
            .logout-link:hover { border-color: var(--sidebar-divider); background: rgba(255,255,255,0.06); }

            .nav-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.35);
                z-index: 40;
                opacity: 0;
                pointer-events: none;
                transition: opacity 180ms ease;
            }
            .nav-backdrop.backdrop-open { opacity: 1; pointer-events: auto; }

            .main-shell {
                width: 100%;
                margin-left: calc(var(--sidebar-w) + 0.5rem);
                transition: margin-left 200ms ease;
            }
            .main-shell .content-inner { max-width: none; margin: 0; width: 100%; }
            .app-shell.sidebar-collapsed .main-shell { margin-left: 0; }
            .app-shell.sidebar-collapsed #sidebar { transform: translateX(-100%); }

            @media (max-width: 1024px) {
                #sidebar.sidebar-v2 { transform: translateX(-100%); box-shadow: 12px 0 28px rgba(0,0,0,0.35); }
                #sidebar.sidebar-v2.sidebar-open { transform: translateX(0); }
                .sidebar-close { display: inline-flex; }
                .main-shell { margin-left: 0; }
                #nav-open-main { display: inline-flex; }
            }
            #nav-open-main.nav-trigger {
                display: inline-flex;
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                color: #0f172a;
                padding: 6px;
                border-radius: 10px;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                margin-right: 4px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }
            html[data-page-theme="dark"] #nav-open-main.nav-trigger,
            .app-shell.app-page-dark #nav-open-main.nav-trigger {
                border-color: rgba(255,255,255,0.25);
                color: #e5e7eb;
                background: rgba(255,255,255,0.06);
            }

            /* Tema escuro da pÃ¡gina */
            html[data-page-theme="dark"] #app-shell,
            #app-shell.app-page-dark { background-color: #0f172a; }
            html[data-page-theme="dark"] .main-shell,
            .main-shell.page-dark { background-color: #0f172a; color: #e2e8f0; }
            html[data-page-theme="dark"] .main-shell header,
            .main-shell.page-dark header { background-color: #0b1220 !important; border-color: #111827 !important; }
            html[data-page-theme="dark"] .main-shell header .text-slate-900,
            html[data-page-theme="dark"] .main-shell header .text-slate-800,
            .main-shell.page-dark header .text-slate-900,
            .main-shell.page-dark header .text-slate-800 { color: #e5e7eb !important; }
            html[data-page-theme="dark"] .main-shell .bg-white,
            .main-shell.page-dark .bg-white { background-color: #0f172a !important; color: #e5e7eb !important; }
            html[data-page-theme="dark"] .main-shell .bg-slate-50,
            .main-shell.page-dark .bg-slate-50 { background-color: #0b1220 !important; }
            html[data-page-theme="dark"] .main-shell .bg-slate-50,
            html[data-page-theme="dark"] .main-shell .bg-slate-100,
            html[data-page-theme="dark"] .main-shell .bg-slate-200,
            html[data-page-theme="dark"] .main-shell .bg-gray-50,
            html[data-page-theme="dark"] .main-shell .bg-gray-100,
            html[data-page-theme="dark"] .main-shell .bg-gray-200,
            html[data-page-theme="dark"] .main-shell .bg-white\/95,
            .main-shell.page-dark .bg-slate-50,
            .main-shell.page-dark .bg-slate-100,
            .main-shell.page-dark .bg-slate-200,
            .main-shell.page-dark .bg-gray-50,
            .main-shell.page-dark .bg-gray-100,
            .main-shell.page-dark .bg-gray-200,
            .main-shell.page-dark .bg-white\/95 { background-color: #111827 !important; color: #e5e7eb !important; }
            html[data-page-theme="dark"] .main-shell .text-slate-900,
            .main-shell.page-dark .text-slate-900 { color: #e5e7eb !important; }
            html[data-page-theme="dark"] .main-shell .text-slate-800,
            html[data-page-theme="dark"] .main-shell .text-slate-700,
            html[data-page-theme="dark"] .main-shell .text-gray-800,
            html[data-page-theme="dark"] .main-shell .text-gray-700,
            .main-shell.page-dark .text-slate-800,
            .main-shell.page-dark .text-slate-700,
            .main-shell.page-dark .text-gray-800,
            .main-shell.page-dark .text-gray-700 { color: #f8fafc !important; }
            html[data-page-theme="dark"] .main-shell .text-slate-600,
            .main-shell.page-dark .text-slate-600 { color: #cbd5e1 !important; }
            html[data-page-theme="dark"] .main-shell .text-slate-500,
            .main-shell.page-dark .text-slate-500 { color: #94a3b8 !important; }
            html[data-page-theme="dark"] .main-shell .border-slate-100,
            html[data-page-theme="dark"] .main-shell .border-slate-200,
            html[data-page-theme="dark"] .main-shell .border-gray-100,
            html[data-page-theme="dark"] .main-shell .border-gray-200,
            html[data-page-theme="dark"] .main-shell .border,
            .main-shell.page-dark .border-slate-100,
            .main-shell.page-dark .border-slate-200,
            .main-shell.page-dark .border-gray-100,
            .main-shell.page-dark .border-gray-200,
            .main-shell.page-dark .border { border-color: #1f2937 !important; }
            html[data-page-theme="dark"] .main-shell input,
            html[data-page-theme="dark"] .main-shell select,
            html[data-page-theme="dark"] .main-shell textarea,
            .main-shell.page-dark input,
            .main-shell.page-dark select,
            .main-shell.page-dark textarea { background-color: #0f172a !important; color: #e5e7eb !important; border-color: #1f2937 !important; }
            html[data-page-theme="dark"] .main-shell .shadow,
            html[data-page-theme="dark"] .main-shell .shadow-sm,
            html[data-page-theme="dark"] .main-shell .shadow-lg,
            .main-shell.page-dark .shadow,
            .main-shell.page-dark .shadow-sm,
            .main-shell.page-dark .shadow-lg { box-shadow: none !important; }
            html[data-page-theme="dark"] .main-shell #page-theme-toggle,
            .main-shell.page-dark #page-theme-toggle { background-color: #0b1220; color: #e5e7eb; border-color: #1f2937; }
            html[data-page-theme="dark"] .main-shell #page-theme-switch,
            .main-shell.page-dark #page-theme-switch { border-color: #1f2937; background-color: #1f2937 !important; }
            html[data-page-theme="dark"] .main-shell #page-theme-toggle .bg-slate-200,
            .main-shell.page-dark #page-theme-toggle .bg-slate-200 { background-color: #1f2937 !important; }
            html[data-page-theme="dark"] .main-shell #page-theme-knob,
            .main-shell.page-dark #page-theme-knob { background-color: #f8fafc; transform: translateX(20px) !important; }
            html[data-page-theme="light"] .main-shell #page-theme-switch { background-color: #e2e8f0 !important; border-color: #cbd5e1 !important; }
            html[data-page-theme="light"] .main-shell #page-theme-knob { transform: translateX(0) !important; }
        </style>
        <title><?= sanitize($title ?: $appName) ?></title>
    </head>
    <body class="overflow-x-hidden">
    <div id="app-shell" class="app-shell flex min-h-screen">
        <aside id="sidebar" class="sidebar-v2">
            <?php
                $navByPage = [];
                foreach ($navItems as $it) {
                    $navByPage[$it['page']] = $it;
                }
                // Ordem: Home, Conteudo, Cadastros, Checklists
                $contexts = [
                    [
                        'id' => 'home',
                        'label' => 'Home',
                        'icon' => 'home',
                        'items' => [
                            ['page' => 'dashboard', 'label' => 'Acesso rapido', 'icon' => 'home'],
                        ],
                    ],
                    [
                        'id' => 'docs',
                        'label' => 'Conteudo',
                        'icon' => 'template',
                        'items' => [
                            ['page' => 'videos', 'label' => 'Videos', 'icon' => 'template'],
                            ['page' => 'revision_logs', 'label' => 'Changelog', 'icon' => 'history'],
                        ],
                    ],
                    [
                        'id' => 'cad',
                        'label' => 'Cadastros',
                        'icon' => 'users',
                        'items' => [
                            ['page' => 'people', 'label' => 'Pessoas', 'icon' => 'users'],
                            ['page' => 'users', 'label' => 'Usuarios', 'icon' => 'user'],
                            ['page' => 'company', 'label' => 'Empresa', 'icon' => 'building'],
                            ['page' => 'branches', 'label' => 'Filiais', 'icon' => 'flag'],
                            ['page' => 'access', 'label' => 'Acessos', 'icon' => 'shield'],
                            ['page' => 'vehicles', 'label' => 'Veiculos', 'icon' => 'truck'],
                            ['page' => 'backup', 'label' => 'Backup DB', 'icon' => 'folder'],
                        ],
                    ],
                    [
                        'id' => 'checklist',
                        'label' => 'Checklists',
                        'icon' => 'list',
                        'items' => [
                            ['page' => 'checks', 'label' => 'Execucoes', 'icon' => 'list'],
                            ['page' => 'templates', 'label' => 'Modelos', 'icon' => 'template'],
                            ['page' => 'groups', 'label' => 'Grupos', 'icon' => 'folder'],
                            ['page' => 'revision_logs', 'label' => 'Revisoes', 'icon' => 'history'],
                        ],
                    ],
                ];
                $contexts = array_values(array_filter(array_map(function ($ctx) use ($navByPage) {
                    $ctx['items'] = array_values(array_filter($ctx['items'], function ($it) use ($navByPage) {
                        return isset($navByPage[$it['page']]);
                    }));
                    return $ctx;
                }, $contexts), function ($ctx) {
                    return !empty($ctx['items']);
                }));
                if (empty($contexts)) {
                    $autoItems = [];
                    foreach ($navByPage as $page => $data) {
                        $autoItems[] = ['page' => $page, 'label' => $data['label'], 'icon' => $data['icon'] ?? 'home'];
                    }
                    $contexts = [[
                        'id' => 'main',
                        'label' => 'Menu',
                        'icon' => 'home',
                        'items' => $autoItems,
                    ]];
                }
                $activePage = $currentPage === '' ? 'dashboard' : $currentPage;
            ?>
            <div class="sidebar-top">
                <div class="brand">
                    <?php if (!empty($company['logo_url'])): ?>
                        <img src="<?= sanitize($company['logo_url']) ?>" alt="logo" class="brand-logo">
                    <?php else: ?>
                        <div class="brand-mark"><?= strtoupper(substr($appName, 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="brand-text">
                        <span class="brand-name"><?= sanitize($company['display_name'] ?: $appName) ?></span>
                        <span class="brand-sub">Painel</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="theme-toggle" id="sidebar-theme-toggle">
                        <span class="theme-switch" id="sidebar-theme-switch">
                            <span class="theme-knob" id="sidebar-theme-knob"></span>
                        </span>
                    </button>
                    <button class="sidebar-close" id="nav-close-mobile" aria-label="Fechar menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="sidebar-scroll">
                <nav class="menu-root" aria-label="Navegacao principal">
                    <?php foreach ($contexts as $ctx): ?>
                        <?php
                            $sectionActive = false;
                            foreach ($ctx['items'] as $it) {
                                if (!isset($navByPage[$it['page']])) {
                                    continue;
                                }
                                $item = $navByPage[$it['page']];
                                $isActive = ($item['page'] === 'dashboard' && ($currentPage === 'dashboard' || $currentPage === '')) || $currentPage === $item['page'];
                                if ($isActive) {
                                    $sectionActive = true;
                                    break;
                                }
                            }
                        ?>
                        <section class="menu-group <?= $sectionActive ? 'open' : '' ?>" data-section="<?= sanitize($ctx['id']) ?>">
                            <button class="menu-group-trigger" type="button" data-section-toggle aria-expanded="<?= $sectionActive ? 'true' : 'false' ?>">
                                <span class="menu-icon"><?= $icon($ctx['icon']) ?></span>
                                <span class="menu-label"><?= sanitize($ctx['label']) ?></span>
                                <span class="menu-chevron">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                </span>
                            </button>
                            <div class="menu-items">
                                <?php foreach ($ctx['items'] as $it): ?>
                                    <?php if (!isset($navByPage[$it['page']])) continue; $item = $navByPage[$it['page']]; ?>
                                    <?php $isActive = ($item['page'] === 'dashboard' && ($currentPage === 'dashboard' || $currentPage === '')) || $currentPage === $item['page']; ?>
                                    <a href="<?= $item['href'] ?>" class="menu-link" data-active="<?= $isActive ? 'true' : 'false' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                                        <span class="menu-dot"></span>
                                        <span class="link-text"><?= sanitize($it['label']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="sidebar-footer">
                <?php if ($user): ?>
                    <a class="menu-link logout-link" href="logout.php">
                        <?= $icon('logout') ?>
                        <span>Logout</span>
                    </a>
                <?php endif; ?>
            </div>
        </aside>

        <div id="nav-backdrop" class="nav-backdrop"></div>

        <div class="main-shell flex-1 flex flex-col transition-all duration-200">
            <header class="sticky top-0 z-30 h-14 bg-white/95 backdrop-blur border-b border-slate-200 flex items-center justify-between px-3 sm:px-4">
                <div class="flex items-center gap-3">
                    <button id="nav-open-main" class="nav-trigger" aria-label="Abrir menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="text-lg font-semibold text-slate-900"><?= sanitize($title ?: $appName) ?></div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="page-theme-toggle" aria-label="Alternar tema da pagina" class="flex items-center gap-1.5 text-xs font-medium bg-white text-slate-700 border border-slate-200 shadow-sm rounded-full px-1.5 py-0.5">
                        <span class="w-8 h-4 bg-slate-200 border border-slate-300 rounded-full relative inline-flex items-center transition-all duration-200" id="page-theme-switch">
                            <span class="absolute left-0.5 top-0.5 w-3 h-3 rounded-full bg-white shadow transition-all duration-200" id="page-theme-knob"></span>
                        </span>
                        <span class="w-4 h-4 rounded-full border border-slate-300 shadow-sm bg-[linear-gradient(90deg,#0b1220_50%,#e5e7eb_50%)]"></span>
                    </button>
                    <?php if ($user): ?>
                        <div class="relative flex items-center gap-2">
                            <div class="text-right leading-tight">
                                <div class="font-semibold text-slate-800"><?= sanitize($user['name']) ?></div>
                                <div class="text-slate-500 text-xs"><?= sanitize(format_role($user['role'])) ?></div>
                            </div>
                            <button id="user-menu-trigger" class="flex items-center gap-2 text-xs sm:text-sm text-slate-600 px-2 py-1 rounded hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-400" type="button" aria-haspopup="true" aria-expanded="false">
                                <?php if (!empty($company['logo_url'])): ?>
                                    <img src="<?= sanitize($company['logo_url']) ?>" alt="logo" class="h-8 w-8 object-contain rounded bg-white border border-slate-200">
                                <?php endif; ?>
                                <span class="sr-only">Menu do usuario</span>
                            </button>
                            <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-lg shadow-lg text-sm z-50">
                                <a href="change_password.php" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-100 border-b border-slate-100">Alterar senha</a>
                                <a href="logout.php" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-100">Logout</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <main class="w-full px-0 py-3">
                <div class="content-inner space-y-4">
                <?php if ($msg = flash('success')): ?>
                    <div class="bg-emerald-100 border border-emerald-300 text-emerald-900 px-4 py-3 rounded"><?= sanitize($msg) ?></div>
                <?php endif; ?>
                <?php if ($msg = flash('error')): ?>
                    <div class="bg-rose-100 border border-rose-300 text-rose-900 px-4 py-3 rounded"><?= sanitize($msg) ?></div>
                <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
                </div>
            </main>
        </div>
    </div>
    <script>
        (function(){
    const appShell = document.getElementById('app-shell');
    const sidebar = document.getElementById('sidebar');
    const navBackdrop = document.getElementById('nav-backdrop');
    const navOpenMain = document.getElementById('nav-open-main');
    const navCloseMobile = document.getElementById('nav-close-mobile');
    const sectionToggles = document.querySelectorAll('[data-section-toggle]');
    const mainShell = document.querySelector('.main-shell');

    const sidebarThemeToggle = document.getElementById('sidebar-theme-toggle');
    const sidebarThemeSwitch = document.getElementById('sidebar-theme-switch');
    const sidebarThemeKnob = document.getElementById('sidebar-theme-knob');

    const pageThemeToggle = document.getElementById('page-theme-toggle');
    const pageThemeSwitch = document.getElementById('page-theme-switch');
    const pageThemeKnob = document.getElementById('page-theme-knob');

    const mobileBreakpoint = 1024;
    let collapsed = localStorage.getItem('erpSidebarCollapsed') === 'true';

    const isMobile = () => window.innerWidth < mobileBreakpoint;

    function setSidebar(open, persist = true) {
        if (!sidebar) return;
        const show = !!open;
        sidebar.classList.toggle('sidebar-open', show);
        navBackdrop?.classList.toggle('backdrop-open', show && isMobile());
        navOpenMain?.setAttribute('aria-expanded', show ? 'true' : 'false');
        const shouldCollapse = !show && !isMobile();
        appShell?.classList.toggle('sidebar-collapsed', shouldCollapse);
        if (persist && !isMobile()) {
            collapsed = !show;
            localStorage.setItem('erpSidebarCollapsed', collapsed ? 'true' : 'false');
        }
    }

    function ensureSidebarState() {
        if (isMobile()) {
            setSidebar(false, false);
        } else {
            setSidebar(!collapsed, false);
        }
    }

    ensureSidebarState();
    window.addEventListener('resize', ensureSidebarState);

    navOpenMain?.addEventListener('click', () => {
        const open = sidebar?.classList.contains('sidebar-open');
        setSidebar(!open);
    });
    navCloseMobile?.addEventListener('click', () => setSidebar(false));
    navBackdrop?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && isMobile()) {
            setSidebar(false);
        }
    });

    sectionToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('.menu-group');
            if (!group) return;
            const next = !group.classList.contains('open');
            // fecha outras se abrindo (accordion)
            if (next) {
                document.querySelectorAll('.menu-group.open').forEach(other => {
                    if (other === group) return;
                    other.classList.remove('open');
                    const trigger = other.querySelector('[data-section-toggle]');
                    trigger?.setAttribute('aria-expanded', 'false');
                });
            }
            group.classList.toggle('open', next);
            btn.setAttribute('aria-expanded', next ? 'true' : 'false');
        });
    });

    document.querySelectorAll('.menu-group').forEach(group => {
        const hasActive = group.querySelector('.menu-link[data-active="true"]');
        if (hasActive) {
            group.classList.add('open');
            const trigger = group.querySelector('[data-section-toggle]');
            trigger?.setAttribute('aria-expanded', 'true');
        }
    });

    document.querySelectorAll('.menu-link[data-active]').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) setSidebar(false);
        });
    });

    function applySidebarTheme(mode) {
        const isLight = mode === 'light';
        document.documentElement.classList.toggle('sidebar-light', isLight);
        document.documentElement.setAttribute('data-sidebar-theme', isLight ? 'light' : 'dark');
        if (sidebarThemeSwitch && sidebarThemeKnob) {
            sidebarThemeSwitch.classList.toggle('bg-slate-200', isLight);
            sidebarThemeSwitch.classList.toggle('bg-slate-700', !isLight);
            sidebarThemeKnob.style.transform = isLight ? 'translateX(20px)' : 'translateX(0)';
        }
    }

    function applyPageTheme(mode) {
        const isDark = mode === 'dark';
        document.documentElement.setAttribute('data-page-theme', isDark ? 'dark' : 'light');
        document.documentElement.classList.remove('page-dark');
        appShell?.classList.toggle('app-page-dark', isDark);
        mainShell?.classList.toggle('page-dark', isDark);
        if (pageThemeSwitch && pageThemeKnob) {
            pageThemeSwitch.classList.toggle('bg-blue-500', isDark);
            pageThemeSwitch.classList.toggle('bg-slate-200', !isDark);
            pageThemeSwitch.classList.toggle('border-slate-300', !isDark);
            pageThemeSwitch.classList.toggle('border-blue-600', isDark);
            pageThemeKnob.style.transform = isDark ? 'translateX(20px)' : 'translateX(0)';
        }
    }

    let sidebarTheme = localStorage.getItem('sidebarTheme');
    if (sidebarTheme !== 'light' && sidebarTheme !== 'dark') {
        sidebarTheme = 'dark';
    }
    applySidebarTheme(sidebarTheme);

    let pageTheme = localStorage.getItem('pageTheme');
    if (pageTheme !== 'light' && pageTheme !== 'dark') {
        pageTheme = 'light';
    }
    applyPageTheme(pageTheme);

    sidebarThemeToggle?.addEventListener('click', () => {
        sidebarTheme = sidebarTheme === 'light' ? 'dark' : 'light';
        applySidebarTheme(sidebarTheme);
        localStorage.setItem('sidebarTheme', sidebarTheme);
    });

    pageThemeToggle?.addEventListener('click', () => {
        pageTheme = pageTheme === 'light' ? 'dark' : 'light';
        applyPageTheme(pageTheme);
        localStorage.setItem('pageTheme', pageTheme);
    });

    const userMenuTrigger = document.getElementById('user-menu-trigger');
    const userMenu = document.getElementById('user-menu');
    function toggleUserMenu(show) {
        if (!userMenu) return;
        const isOpen = show !== undefined ? show : userMenu.classList.contains('hidden');
        userMenu.classList.toggle('hidden', !isOpen);
        if (userMenuTrigger) {
            userMenuTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }
    userMenuTrigger?.addEventListener('click', (e) => {
        e.preventDefault();
        toggleUserMenu();
    });
    document.addEventListener('click', (e) => {
        if (!userMenu || !userMenuTrigger) return;
        if (!userMenu.contains(e.target) && !userMenuTrigger.contains(e.target)) {
            toggleUserMenu(false);
        }
    });
})();;;
    </script>
    </body>
    </html>
    <?php
}
