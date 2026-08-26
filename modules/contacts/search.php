<?php

use App\Core\Permission;
use Modules\Contacts\Models\Directory;

/** البحث الموحّد: جهات وأفراد الدليل. */
return function (array $user, string $query): array {
    if (!Permission::check('contacts.view') || empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $results = [];

    foreach (Directory::organizations($companyId, ['q' => $query], 6) as $o) {
        $results[] = [
            'title' => '🏢 ' . $o['name'],
            'subtitle' => trim(($o['kind'] ?? '') . ' · ' . ($o['city'] ?? ''), ' ·'),
            'url' => route('/contacts/orgs/' . $o['id']),
        ];
    }
    foreach (Directory::persons($companyId, ['q' => $query], 6) as $p) {
        $results[] = [
            'title' => '👤 ' . $p['full_name'],
            'subtitle' => trim(($p['job_title'] ?? '') . ' · ' . ($p['main_org'] ?? 'مستقل'), ' ·'),
            'url' => route('/contacts/people/' . $p['id']),
        ];
    }
    return $results;
};
