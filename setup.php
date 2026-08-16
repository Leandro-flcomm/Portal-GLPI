<?php

define('PLUGIN_FLCPORTAL_VERSION', '1.0.0');
define('PLUGIN_FLCPORTAL_MIN_GLPI', '11.0.0');
define('PLUGIN_FLCPORTAL_MAX_GLPI', '12.0.0');

function plugin_version_flcportal(): array {
    return [
        'name'         => 'FLComm Self-Service Portal',
        'version'      => PLUGIN_FLCPORTAL_VERSION,
        'author'       => 'FLComm TI',
        'license'      => 'GPL v2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_FLCPORTAL_MIN_GLPI,
                'max' => PLUGIN_FLCPORTAL_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_flcportal_check_prerequisites(): bool {
    return true;
}

function plugin_flcportal_check_config(bool $verbose = false): bool {
    return true;
}

function plugin_init_flcportal(): void {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['flcportal'] = true;

    // Register always — the hook function itself checks session/interface
    $PLUGIN_HOOKS['post_init']['flcportal'] = 'plugin_flcportal_post_init';
}

function plugin_flcportal_install(): bool {
    return true;
}

function plugin_flcportal_uninstall(): bool {
    return true;
}
