<?php
/** مزوّد البحث الموحّد: يبحث بأسماء العملاء وهواتفهم. */
return function (array $user, string $query): array {
    if (empty($user['company_id']) || !\App\Core\Permission::check('clients.view')) {
        return [];
    }
    $rows = \App\Core\Database::select(
        "SELECT id, name, phone FROM clients_clients
          WHERE company_id = :c AND status = 'active' AND (name LIKE :q OR phone LIKE :q2 OR contact_name LIKE :q3)
          ORDER BY name LIMIT 10",
        ['c' => $user['company_id'], 'q' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%"]
    );
    return array_map(fn ($r) => [
        'title' => '👔 ' . $r['name'],
        'subtitle' => $r['phone'] ?: '',
        'url' => route('/clients/' . $r['id']),
    ], $rows);
};
