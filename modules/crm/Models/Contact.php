<?php

namespace Modules\Crm\Models;

use App\Core\Auth;
use Modules\Contacts\Models\Directory;

/**
 * أشخاص الجهة في CRM هم أفراد دليل «جهات الاتصال» المرتبطون بها. فمن يُضاف هنا
 * يصبح فرداً في دليل الشركة (لا نسخة خاصة بـ CRM)، ومن يُزال هنا يُفكّ ارتباطه
 * بالجهة فقط ويبقى في الدليل - لأن حذفه من الشركة قرار أكبر من مساحة CRM.
 */
class Contact
{
    public static function find(int $id): ?array
    {
        $person = Directory::findPerson($id);
        if (!$person) {
            return null;
        }
        // organization_id لأول جهة مرتبطة - للتوافق مع استدعاءات CRM القديمة
        $orgs = Directory::orgsOf($id);
        $person['organization_id'] = $orgs[0]['id'] ?? null;
        return $person;
    }

    /** هل هذا الفرد مرتبط بهذه الجهة فعلاً؟ */
    public static function belongsTo(int $personId, int $organizationId): bool
    {
        foreach (Directory::orgsOf($personId) as $org) {
            if ((int) $org['id'] === $organizationId) {
                return true;
            }
        }
        return false;
    }

    /** أشخاص الجهة بمسمّياتهم فيها (الاسم name للتوافق مع واجهات CRM). */
    public static function forOrganization(int $organizationId): array
    {
        return array_map(function (array $p) {
            $p['name'] = $p['full_name'];
            $p['job_title'] = $p['role_title'] ?: $p['job_title'];
            $p['department'] = $p['role_department'] ?: null;
            return $p;
        }, Directory::peopleOf($organizationId));
    }

    /** إضافة شخص للجهة: يُنشأ في الدليل ويُربط بها. */
    public static function create(array $data): int
    {
        $organizationId = (int) ($data['organization_id'] ?? 0);
        $jobTitle = $data['job_title'] ?? null;
        $department = $data['department'] ?? null;

        $personId = Directory::createPerson([
            'company_id' => (int) $data['company_id'],
            'full_name' => $data['name'] ?? ($data['full_name'] ?? ''),
            'job_title' => $jobTitle,
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'linkedin' => $data['linkedin'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? Auth::id(),
        ]);
        if ($organizationId) {
            Directory::link($personId, $organizationId, $jobTitle, $department, false);
        }
        return $personId;
    }

    public static function update(int $id, array $data): void
    {
        $person = [
            'full_name' => $data['name'] ?? ($data['full_name'] ?? null),
            'job_title' => $data['job_title'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'linkedin' => $data['linkedin'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        Directory::updatePerson($id, array_filter($person, fn ($v) => $v !== null));

        // المسمّى داخل الجهة صفة للعلاقة لا للفرد
        if (!empty($data['organization_id'])) {
            Directory::link($id, (int) $data['organization_id'], $data['job_title'] ?? null, $data['department'] ?? null, false);
        }
    }

    /** إزالة الشخص من الجهة = فكّ الارتباط، ويبقى في دليل الشركة. */
    public static function detach(int $personId, int $organizationId): void
    {
        Directory::unlink($personId, $organizationId);
    }
}
