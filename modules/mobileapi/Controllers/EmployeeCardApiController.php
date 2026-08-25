<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Permission;
use Modules\Mobileapi\Support\Api;

/**
 * البطاقة الشخصية للموظف: نفس بيانات بطاقة الويب (اسم، مسمى، إدارة، جوال،
 * صورة، هوية الشركة)، لكن رمز QR يُعاد كنصّ vCard خام ليرسمه التطبيق أصلياً
 * بدل صورة جاهزة - فيبقى حاداً على أي كثافة شاشة.
 *
 * الصور تُبَثّ من هنا لا من مسار /media لأن ذاك يتحقق بالجلسة، والتطبيق
 * يحمل توكن Bearer فقط.
 *
 * قاعدة الرؤية نفسها في الويب: صاحب الملف، أو من يملك مشاهدة الملفات.
 */
class EmployeeCardApiController
{
    /** GET /api/v1/employee-card - بطاقة المستخدم الحالي. */
    public function mine(): void
    {
        [$employee, $companyId] = $this->ownProfile();
        $this->respondWith($employee, $companyId);
    }

    /** GET /api/v1/employee-card/photo - صورة صاحب البطاقة الحالية. */
    public function minePhoto(): void
    {
        [$employee, $companyId] = $this->ownProfile();
        $this->streamPhoto($employee, $companyId);
    }

    /** GET /api/v1/employee-card/logo - شعار الشركة. */
    public function logo(): void
    {
        $companyId = $this->companyId();
        $company = Database::first('SELECT logo FROM companies WHERE id = :id', ['id' => $companyId]);
        if (empty($company['logo'])) {
            Api::error('لا يوجد شعار للشركة.', 404, 'not_found');
        }
        $this->stream(BASE_PATH . '/storage/uploads/companies/' . $company['logo']);
    }

    /** GET /api/v1/employee-card/{id} - بطاقة موظف بعينه. */
    public function show(array $params): void
    {
        [$employee, $companyId] = $this->visibleProfile((int) $params['id']);
        $this->respondWith($employee, $companyId);
    }

    /** GET /api/v1/employee-card/{id}/photo */
    public function photo(array $params): void
    {
        [$employee, $companyId] = $this->visibleProfile((int) $params['id']);
        $this->streamPhoto($employee, $companyId);
    }

    // ---------------------------------------------------------------- مساعدات

    private function companyId(): int
    {
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            Api::error('لا توجد شركة مرتبطة بحسابك.', 400, 'no_company');
        }
        return $companyId;
    }

    /** @return array{0: array, 1: int} */
    private function ownProfile(): array
    {
        $companyId = $this->companyId();
        $employee = Database::first(
            'SELECT * FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
            ['c' => $companyId, 'u' => Auth::id()]
        );
        if (!$employee) {
            Api::error(
                'حسابك غير مربوط بملف وظيفي — اطلب من الإدارة ربطه لعرض بطاقتك.',
                404,
                'no_profile'
            );
        }
        return [$employee, $companyId];
    }

    /** @return array{0: array, 1: int} */
    private function visibleProfile(int $id): array
    {
        $companyId = $this->companyId();
        $employee = Database::first(
            'SELECT * FROM employees_profiles WHERE id = :id AND company_id = :c',
            ['id' => $id, 'c' => $companyId]
        );
        if (!$employee) {
            Api::error('الملف الوظيفي غير موجود.', 404, 'not_found');
        }

        $isOwn = !empty($employee['linked_user_id']) && (int) $employee['linked_user_id'] === (int) Auth::id();
        if (!$isOwn && !Permission::check('employees.view')) {
            Api::error('لا تملك صلاحية عرض هذه البطاقة.', 403, 'forbidden');
        }

        return [$employee, $companyId];
    }

    private function respondWith(array $employee, int $companyId): void
    {
        $company = Database::first(
            'SELECT name, logo, primary_color FROM companies WHERE id = :id',
            ['id' => $companyId]
        ) ?: [];

        Api::ok([
            'employee' => [
                'id' => (int) $employee['id'],
                'full_name' => $employee['full_name'] ?? '',
                'job_title' => $employee['job_title'] ?: null,
                'department' => $employee['department'] ?: null,
                'phone' => $employee['phone'] ?: null,
                'hire_date' => $employee['hire_date'] ?: null,
                'has_photo' => !empty($employee['photo']),
            ],
            'company' => [
                'name' => $company['name'] ?? '',
                'has_logo' => !empty($company['logo']),
                'primary_color' => $company['primary_color'] ?? null,
            ],
            // بطاقة تواصل يحفظها من يمسح الرمز في جهات اتصاله.
            'vcard' => $this->vcard($employee, $company['name'] ?? ''),
        ]);
    }

    private function streamPhoto(array $employee, int $companyId): void
    {
        if (empty($employee['photo'])) {
            Api::error('لا توجد صورة لهذا الموظف.', 404, 'not_found');
        }
        $this->stream(BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $employee['photo']);
    }

    private function stream(string $path): void
    {
        if (!is_file($path)) {
            Api::error('الملف غير موجود على الخادم.', 404, 'file_missing');
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    private function vcard(array $employee, string $companyName): string
    {
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . ($employee['full_name'] ?? '')];
        if (!empty($employee['job_title'])) {
            $lines[] = 'TITLE:' . $employee['job_title'];
        }
        if ($companyName !== '') {
            $lines[] = 'ORG:' . $companyName;
        }
        if (!empty($employee['phone'])) {
            $lines[] = 'TEL:' . $employee['phone'];
        }
        $lines[] = 'END:VCARD';

        return implode("\n", $lines);
    }
}
