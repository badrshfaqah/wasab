<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Permission;
use Modules\Mobileapi\Support\Api;
use Modules\Mobileapi\Support\ApplePass;

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

    /**
     * GET /api/v1/employee-card/pass - بطاقة المستخدم بصيغة Apple Wallet.
     *
     * تُعاد كبايتات .pkpass موقّعة، يفتحها التطبيق بـ PassKit مباشرة.
     */
    public function pass(): void
    {
        if (!ApplePass::isConfigured()) {
            Api::error(
                'إضافة البطاقة إلى Apple Wallet غير مهيّأة على هذا الخادم بعد.',
                503,
                'pass_not_configured'
            );
        }

        [$employee, $companyId] = $this->ownProfile();
        $company = $this->company($companyId);

        try {
            $bytes = ApplePass::build(
                $employee,
                $company,
                $companyId,
                $this->vcard($employee, $company, $companyId),
                $this->email($employee)
            );
        } catch (\Throwable $e) {
            log_exception($e);
            Api::error('تعذّر إصدار بطاقة المحفظة. راجع سجلّ الأخطاء على الخادم.', 500, 'pass_failed');
        }

        header('Content-Type: application/vnd.apple.pkpass');
        header('Content-Disposition: attachment; filename="wasab-employee-card.pkpass"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        echo $bytes;
        exit;
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

    private function company(int $companyId): array
    {
        return Database::first(
            'SELECT name, logo, primary_color FROM companies WHERE id = :id',
            ['id' => $companyId]
        ) ?: [];
    }

    private function respondWith(array $employee, int $companyId): void
    {
        $company = $this->company($companyId);
        $isOwn = !empty($employee['linked_user_id']) && (int) $employee['linked_user_id'] === (int) Auth::id();

        Api::ok([
            'employee' => [
                'id' => (int) $employee['id'],
                'full_name' => $employee['full_name'] ?? '',
                'job_title' => $employee['job_title'] ?: null,
                'department' => $employee['department'] ?: null,
                'phone' => $employee['phone'] ?: null,
                'email' => $this->email($employee),
                'hire_date' => $employee['hire_date'] ?: null,
                'has_photo' => !empty($employee['photo']),
            ],
            'company' => [
                'name' => $company['name'] ?? '',
                'has_logo' => !empty($company['logo']),
                'primary_color' => $company['primary_color'] ?? null,
                'website' => ApplePass::website($companyId),
            ],
            // بطاقة تواصل يحفظها من يمسح الرمز في جهات اتصاله.
            'vcard' => $this->vcard($employee, $company, $companyId),
            // زر المحفظة لبطاقة صاحبها فقط، وحين يكون الخادم مهيّأً بشهادة أبل.
            'has_wallet_pass' => $isOwn && ApplePass::isConfigured(),
        ]);
    }

    /**
     * بريد العمل من حساب الموظف في النظام، وإلا بريده الشخصي في ملفه.
     */
    private function email(array $employee): ?string
    {
        if (!empty($employee['linked_user_id'])) {
            $user = Database::first(
                'SELECT email FROM users WHERE id = :id',
                ['id' => (int) $employee['linked_user_id']]
            );
            if (!empty($user['email'])) {
                return $user['email'];
            }
        }

        return $employee['personal_email'] ?: null;
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

    /**
     * بطاقة تواصل كاملة: من يمسح الرمز يحفظ الاسم والمسمى والجوال والبريد
     * وموقع الشركة دفعة واحدة - لا اسماً مجرّداً يضطر لكتابة الباقي بيده.
     *
     * الأنواع (CELL / WORK) تجعل الهاتف يصنّف الحقول في جهة الاتصال صحيحاً.
     */
    private function vcard(array $employee, array $company, int $companyId): string
    {
        $companyName = (string) ($company['name'] ?? '');

        $lines = ['BEGIN:VCARD', 'VERSION:3.0'];
        $lines[] = 'FN:' . ($employee['full_name'] ?? '');
        if (!empty($employee['job_title'])) {
            $lines[] = 'TITLE:' . $employee['job_title'];
        }
        if ($companyName !== '') {
            // القسم الثاني في ORG هو الإدارة، وهكذا تقرأه تطبيقات جهات الاتصال.
            $lines[] = 'ORG:' . $companyName
                . (!empty($employee['department']) ? ';' . $employee['department'] : '');
        }
        if (!empty($employee['phone'])) {
            $lines[] = 'TEL;TYPE=CELL,VOICE:' . $employee['phone'];
        }
        $email = $this->email($employee);
        if ($email) {
            $lines[] = 'EMAIL;TYPE=WORK,INTERNET:' . $email;
        }
        $website = ApplePass::website($companyId);
        if ($website !== '') {
            $lines[] = 'URL:' . $website;
        }
        $lines[] = 'END:VCARD';

        return implode("\n", $lines);
    }
}
