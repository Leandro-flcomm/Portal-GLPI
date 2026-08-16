<?php

class PluginFlcportalPortal {

    // Themes by entity_id (0=FLComm, 1=Asso, 2=Camorim)
    private const ENTITY_THEMES = [
        0 => ['sidebar' => '#0a2347', 'accent' => '#2563EB', 'name' => 'FLComm'],
        1 => ['sidebar' => '#1a3a5c', 'accent' => '#1a56b0', 'name' => 'Asso Marítima'],
        2 => ['sidebar' => '#7f1d1d', 'accent' => '#c0392b', 'name' => 'Camorim'],
    ];

    private const DEFAULT_THEME = [
        'sidebar' => '#0a2347',
        'accent'  => '#2563EB',
        'name'    => 'FLComm',
    ];

    private const ENTITY_LOGOS = [
        0 => '/pics/logos/flcomm_logo.png',
        1 => '/pics/logos/asso_logo.png',
        2 => '/pics/logos/camorim_logo.png',
    ];

    public static function getEntityTheme(): array {
        $entity_id = (int)($_SESSION['glpiactive_entity'] ?? 0);
        return self::ENTITY_THEMES[$entity_id] ?? self::DEFAULT_THEME;
    }

    public static function getEntityLogoPath(): string {
        $entity_id = (int)($_SESSION['glpiactive_entity'] ?? 0);
        return self::ENTITY_LOGOS[$entity_id] ?? self::ENTITY_LOGOS[0];
    }

    public static function getUserDisplayName(): string {
        $first = trim($_SESSION['glpifirstname'] ?? '');
        $last  = trim($_SESSION['glpilastname'] ?? '');
        $full  = trim("$first $last");
        return $full ?: ($_SESSION['glpiname'] ?? 'Usuário');
    }

    public static function getUserInitial(): string {
        return mb_strtoupper(mb_substr(self::getUserDisplayName(), 0, 1));
    }

    /**
     * Returns root catalog categories visible to helpdesk.
     */
    public static function getCatalogCategories(): array {
        global $DB;

        $entity_id = (int)($_SESSION['glpiactive_entity'] ?? 0);

        $where = [
            'is_helpdeskvisible' => 1,
            'itilcategories_id'  => 0,
        ];
        $where += getEntitiesRestrictCriteria(
            ITILCategory::getTable(), '', $entity_id, true
        );

        $iterator = $DB->request([
            'FROM'  => ITILCategory::getTable(),
            'WHERE' => $where,
            'ORDER' => ['name ASC'],
        ]);

        return iterator_to_array($iterator);
    }

    /**
     * Returns sub-items of a category.
     */
    public static function getCatalogItemsByCategory(int $category_id): array {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => ITILCategory::getTable(),
            'WHERE' => [
                'is_helpdeskvisible' => 1,
                'itilcategories_id'  => $category_id,
            ],
            'ORDER' => ['name ASC'],
        ]);

        return iterator_to_array($iterator);
    }

    /**
     * Returns tickets for the logged-in user, most recent first.
     */
    public static function getUserTickets(int $limit = 50): array {
        global $DB;

        $user_id = (int)($_SESSION['glpiID'] ?? 0);

        $iterator = $DB->request([
            'SELECT' => [
                't.id',
                't.name',
                't.status',
                't.date',
                't.date_mod',
                't.closedate',
                't.itilcategories_id',
            ],
            'FROM'       => Ticket::getTable() . ' AS t',
            'INNER JOIN' => [
                'glpi_tickets_users AS tu' => [
                    'ON' => ['tu' => 'tickets_id', 't' => 'id'],
                ],
            ],
            'WHERE' => [
                'tu.users_id' => $user_id,
                'tu.type'     => CommonITILActor::REQUESTER,
                't.is_deleted' => 0,
            ],
            'ORDER' => ['t.date_mod DESC'],
            'LIMIT' => $limit,
        ]);

        return iterator_to_array($iterator);
    }

    /**
     * Returns status label for display.
     */
    public static function getStatusLabel(int $status): string {
        return match ($status) {
            Ticket::INCOMING => 'Novo',
            Ticket::ASSIGNED => 'Em andamento',
            Ticket::PLANNED  => 'Planejado',
            Ticket::WAITING  => 'Pendente',
            Ticket::SOLVED   => 'Resolvido',
            Ticket::CLOSED   => 'Fechado',
            default          => 'Desconhecido',
        };
    }

    /**
     * Returns CSS badge class for status.
     */
    public static function getStatusClass(int $status): string {
        return match ($status) {
            Ticket::INCOMING                  => 'badge-new',
            Ticket::ASSIGNED, Ticket::PLANNED => 'badge-progress',
            Ticket::WAITING                   => 'badge-waiting',
            Ticket::SOLVED                    => 'badge-resolved',
            Ticket::CLOSED                    => 'badge-closed',
            default                           => 'badge-new',
        };
    }

    /**
     * Creates a ticket from catalog. Returns ID or false on error.
     */
    public static function createTicket(array $post): int|false {
        $title       = trim(strip_tags($post['title'] ?? ''));
        $description = trim(strip_tags($post['description'] ?? ''));
        $category_id = (int)($post['category_id'] ?? 0);

        if ($title === '' || $description === '') {
            return false;
        }

        $ticket = new Ticket();
        $id = $ticket->add([
            'name'                => $title,
            'content'             => $description,
            'itilcategories_id'   => $category_id,
            'type'                => Ticket::INCIDENT_TYPE,
            'urgency'             => 3,
            'impact'              => 3,
            'priority'            => 3,
            'requesttypes_id'     => 1,
            'entities_id'         => (int)($_SESSION['glpiactive_entity'] ?? 0),
            '_users_id_requester' => (int)($_SESSION['glpiID'] ?? 0),
        ]);

        return $id ?: false;
    }

    /**
     * Formats date for friendly display.
     */
    public static function formatDate(string $date): string {
        if (empty($date) || $date === 'NULL' || str_starts_with($date, '0000-')) {
            return '—';
        }
        try {
            $dt = new DateTime($date);
            return $dt->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return '—';
        }
    }
}
