<?php

// Bootstrap GLPI
define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));
include GLPI_ROOT . '/inc/includes.php';

// Validate session — redirects to login if invalid
Session::checkValidSessionId();

// Admins should not be trapped here
if (Session::getCurrentInterface() !== 'helpdesk') {
    Html::redirect($CFG_GLPI['root_doc'] . '/front/central.php');
    exit;
}

// ── Global data ──
$theme    = PluginFlcportalPortal::getEntityTheme();
$username = PluginFlcportalPortal::getUserDisplayName();
$initial  = PluginFlcportalPortal::getUserInitial();
$logo     = PluginFlcportalPortal::getEntityLogoPath();
$entity   = $theme['name'];

// ── Routing ──
$action      = $_GET['action'] ?? 'home';
$category_id = (int)($_GET['category_id'] ?? 0);
$created     = isset($_GET['created']) && $_GET['created'] === '1';

$allowed_actions = ['home', 'catalog', 'category', 'new_ticket', 'tickets'];
if (!in_array($action, $allowed_actions, true)) {
    $action = 'home';
}

// ── Handle ticket creation (POST) ──
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new_ticket') {
    Session::checkCSRF($_POST);
    $new_id = PluginFlcportalPortal::createTicket($_POST);
    if ($new_id !== false) {
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/flcportal/front/portal.php?action=tickets&created=1');
        exit;
    }
    $error_msg = 'Erro ao criar chamado. Verifique os campos e tente novamente.';
}

// ── Per-page data ──
$categories  = [];
$sub_items   = [];
$tickets     = [];
$parent_cat  = null;

if (in_array($action, ['home', 'catalog'])) {
    $categories = PluginFlcportalPortal::getCatalogCategories();
}

if ($action === 'category' && $category_id > 0) {
    $sub_items  = PluginFlcportalPortal::getCatalogItemsByCategory($category_id);
    $cat        = new ITILCategory();
    $cat->getFromDB($category_id);
    $parent_cat = $cat->fields;
}

if ($action === 'tickets') {
    $tickets = PluginFlcportalPortal::getUserTickets();
}

// ── Base URLs ──
$base_url   = $CFG_GLPI['root_doc'] . '/plugins/flcportal/front/portal.php';
$glpi_root  = $CFG_GLPI['root_doc'];
$logout_url = $glpi_root . '/front/logout.php';
$csrf_token = Session::getNewCSRFToken();

// ── Page title by action ──
$page_titles = [
    'home'       => ['title' => "Bem-vindo, " . explode(' ', $username)[0] . " 👋", 'sub' => 'Como podemos ajudar hoje?'],
    'catalog'    => ['title' => 'Catálogo de Serviços', 'sub' => 'Selecione uma categoria'],
    'category'   => ['title' => $parent_cat['name'] ?? 'Categoria', 'sub' => 'Selecione o serviço desejado'],
    'new_ticket' => ['title' => 'Novo Chamado', 'sub' => 'Preencha os campos abaixo'],
    'tickets'    => ['title' => 'Meus Chamados', 'sub' => 'Acompanhe suas solicitações'],
];
$pt = $page_titles[$action] ?? $page_titles['home'];

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($entity) ?> — Portal de Suporte</title>
    <link rel="stylesheet" href="<?= $glpi_root ?>/plugins/flcportal/css/portal.css">
    <style>
        :root {
            --sidebar-bg: <?= htmlspecialchars($theme['sidebar']) ?>;
            --accent:     <?= htmlspecialchars($theme['accent']) ?>;
        }
    </style>
</head>
<body>

<div class="portal-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="<?= $glpi_root . htmlspecialchars($logo) ?>"
                 alt="<?= htmlspecialchars($entity) ?>">
            <div class="entity-sub">Portal de Suporte</div>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= $base_url ?>?action=home"
               class="nav-item <?= $action === 'home' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> Início
            </a>
            <a href="<?= $base_url ?>?action=catalog"
               class="nav-item <?= in_array($action, ['catalog','category','new_ticket']) ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> Catálogo de Serviços
            </a>
            <a href="<?= $base_url ?>?action=tickets"
               class="nav-item <?= $action === 'tickets' ? 'active' : '' ?>">
                <span class="nav-icon">🎫</span> Meus Chamados
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-entity"><?= htmlspecialchars($entity) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main-area">
        <header class="topbar">
            <div>
                <h1 class="topbar-title"><?= htmlspecialchars($pt['title']) ?></h1>
                <p class="topbar-sub"><?= htmlspecialchars($pt['sub']) ?></p>
            </div>
            <a href="<?= $logout_url ?>" class="logout-btn">Sair</a>
        </header>

        <main class="content">

            <?php if ($created): ?>
                <div class="alert alert-success">
                    ✅ Chamado criado com sucesso! Você receberá atualizações por e-mail.
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    ⚠️ <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- ── HOME / CATALOG ── -->
            <?php if (in_array($action, ['home', 'catalog'])): ?>

                <div class="section-title">Catálogo de Serviços</div>

                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <p>Nenhuma categoria de serviço disponível.</p>
                        <p style="margin-top:6px;font-size:12px;">
                            Contate o administrador para configurar o catálogo.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="catalog-grid">
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= $base_url ?>?action=category&category_id=<?= (int)$cat['id'] ?>"
                               class="catalog-card">
                                <span class="cat-icon">📁</span>
                                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Recent tickets on home -->
                <?php if ($action === 'home'):
                    $recent = PluginFlcportalPortal::getUserTickets(3);
                    if (!empty($recent)):
                ?>
                    <div class="section-title" style="margin-top:8px;">Últimas Solicitações</div>
                    <div class="ticket-list">
                        <?php foreach ($recent as $t): ?>
                            <div class="ticket-item">
                                <span class="ticket-badge <?= PluginFlcportalPortal::getStatusClass((int)$t['status']) ?>">
                                    <?= htmlspecialchars(PluginFlcportalPortal::getStatusLabel((int)$t['status'])) ?>
                                </span>
                                <div class="ticket-info">
                                    <div class="ticket-title"><?= htmlspecialchars($t['name']) ?></div>
                                    <div class="ticket-meta">
                                        <?= htmlspecialchars(PluginFlcportalPortal::formatDate($t['date_mod'])) ?>
                                    </div>
                                </div>
                                <span class="ticket-number">#<?= (int)$t['id'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; endif; ?>

            <!-- ── CATEGORY (sub-items) ── -->
            <?php elseif ($action === 'category'): ?>

                <div class="breadcrumb">
                    <a href="<?= $base_url ?>?action=catalog">Catálogo</a>
                    <span>›</span>
                    <span><?= htmlspecialchars($parent_cat['name'] ?? '') ?></span>
                </div>

                <?php if (empty($sub_items)): ?>
                    <!-- Category with no children = direct service -->
                    <a href="<?= $base_url ?>?action=new_ticket&category_id=<?= $category_id ?>"
                       class="btn-submit" style="text-decoration:none;display:inline-block;margin-bottom:16px;">
                        + Abrir chamado nesta categoria
                    </a>
                <?php else: ?>
                    <div class="section-title">Selecione o serviço</div>
                    <div class="catalog-grid">
                        <?php foreach ($sub_items as $item): ?>
                            <a href="<?= $base_url ?>?action=new_ticket&category_id=<?= (int)$item['id'] ?>"
                               class="catalog-card">
                                <span class="cat-icon">🔧</span>
                                <div class="cat-name"><?= htmlspecialchars($item['name']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <!-- ── NEW TICKET FORM ── -->
            <?php elseif ($action === 'new_ticket'): ?>

                <?php
                    $form_cat = new ITILCategory();
                    $cat_name = '';
                    if ($category_id > 0 && $form_cat->getFromDB($category_id)) {
                        $cat_name = $form_cat->fields['name'];
                    }
                ?>

                <div class="breadcrumb">
                    <a href="<?= $base_url ?>?action=catalog">Catálogo</a>
                    <span>›</span>
                    <?php if ($cat_name): ?>
                        <a href="<?= $base_url ?>?action=category&category_id=<?= $category_id ?>">
                            <?= htmlspecialchars($cat_name) ?>
                        </a>
                        <span>›</span>
                    <?php endif; ?>
                    <span>Novo chamado</span>
                </div>

                <div class="ticket-form">
                    <form method="post"
                          action="<?= $base_url ?>?action=new_ticket&category_id=<?= $category_id ?>">
                        <input type="hidden" name="_glpi_csrf_token"
                               value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="category_id"
                               value="<?= $category_id ?>">

                        <div class="form-group">
                            <label for="title">Título *</label>
                            <input type="text" id="title" name="title"
                                   placeholder="Descreva brevemente o problema"
                                   value="<?= $cat_name ? htmlspecialchars($cat_name) : '' ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="description">Descrição *</label>
                            <textarea id="description" name="description"
                                      placeholder="Detalhe o problema: o que aconteceu, quando começou, o que você já tentou..."
                                      required></textarea>
                        </div>

                        <div style="margin-top:8px;">
                            <a href="<?= $base_url ?>?action=catalog" class="btn-back">← Voltar</a>
                            <button type="submit" class="btn-submit">Enviar Chamado</button>
                        </div>
                    </form>
                </div>

            <!-- ── MY TICKETS ── -->
            <?php elseif ($action === 'tickets'): ?>

                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎫</div>
                        <p>Você ainda não tem chamados abertos.</p>
                        <a href="<?= $base_url ?>?action=catalog"
                           style="margin-top:14px;display:inline-block;color:var(--accent);font-size:13px;font-weight:600;">
                            Abrir primeiro chamado →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="ticket-list">
                        <?php foreach ($tickets as $t): ?>
                            <div class="ticket-item">
                                <span class="ticket-badge <?= PluginFlcportalPortal::getStatusClass((int)$t['status']) ?>">
                                    <?= htmlspecialchars(PluginFlcportalPortal::getStatusLabel((int)$t['status'])) ?>
                                </span>
                                <div class="ticket-info">
                                    <div class="ticket-title"><?= htmlspecialchars($t['name']) ?></div>
                                    <div class="ticket-meta">
                                        Atualizado em <?= htmlspecialchars(PluginFlcportalPortal::formatDate($t['date_mod'])) ?>
                                    </div>
                                </div>
                                <span class="ticket-number">#<?= (int)$t['id'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div><!-- /main-area -->

</div><!-- /portal-layout -->

<script src="<?= $glpi_root ?>/plugins/flcportal/js/portal.js"></script>
</body>
</html>
