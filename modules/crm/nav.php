<?php
/** رابط القائمة الجانبية لإضافة CRM. */
return function (array $user): array {
    if (\App\Core\Permission::check('crm.view')) {
        return [
            ['label' => 'إدارة العلاقات', 'icon' => '🤝', 'url' => route('/crm')],
            ['label' => 'عملي اليوم (CRM)', 'icon' => '📌', 'url' => route('/crm/today')],
        ];
    }
    return [];
};
