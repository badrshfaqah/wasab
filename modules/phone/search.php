<?php

use App\Core\Permission;
use Modules\Phone\Models\PhoneContact;

/**
 * مزوّد البحث الموحّد من إضافة الهاتف: يعيد استخدام PhoneContact::forUser() بنفس منطق
 * رؤية جهات الاتصال المعتمد أصلاً (عامة + خاصة به + خاصة الكل لمدير الشركة/النظام).
 */
return function (array $user, string $query): array {
    if (!Permission::check('phone.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $isCompanyAdminOrAbove = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin';

    $rows = array_slice(PhoneContact::forUser($companyId, $userId, $isCompanyAdminOrAbove, $query), 0, 8);

    return array_map(fn ($c) => [
        'title' => $c['linked_user_name'] ?? $c['name'],
        'subtitle' => $c['phone_number'] ?: $c['linked_extension'],
        'icon' => '📞',
        'url' => route('/phone/contacts'),
    ], $rows);
};
