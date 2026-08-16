<?php

function plugin_flcportal_post_init(): void {
    if (!isset($_SESSION['glpiID'])) {
        return;
    }

    if (Session::getCurrentInterface() !== 'helpdesk') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Don't redirect essential routes or the portal itself
    $bypass = [
        'flcportal',
        '/ajax/',
        '/api',
        '/front/login',
        '/front/logout',
        '/front/cron',
        'logout',
        'keepalive',
    ];

    foreach ($bypass as $pattern) {
        if (str_contains($uri, $pattern)) {
            return;
        }
    }

    global $CFG_GLPI;
    $portal_url = $CFG_GLPI['root_doc'] . '/plugins/flcportal/front/portal.php';
    header('Location: ' . $portal_url, true, 302);
    exit();
}
