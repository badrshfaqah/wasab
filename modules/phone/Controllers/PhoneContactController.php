<?php

namespace Modules\Phone\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Phone\Models\PhoneContact;

class PhoneContactController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardAccess();

        $search = trim((string) Request::query('q', ''));
        $isCompanyAdminOrAbove = Auth::isSystemAdmin() || Auth::isCompanyAdmin();
        $contacts = PhoneContact::forUser($companyId, Auth::id(), $isCompanyAdminOrAbove, $search ?: null);

        View::render('phone::contacts.index', [
            'pageTitle' => 'دليل جهات الاتصال',
            'contacts' => $contacts,
            'search' => $search,
            'canManagePublic' => $this->canManagePublic(),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardAccess();

        View::render('phone::contacts.form', [
            'pageTitle' => 'جهة اتصال جديدة',
            'contact' => null,
            'companyUsers' => PhoneContact::companyExtensionUsers($companyId),
            'canManagePublic' => $this->canManagePublic(),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardAccess();
        $this->verifyCsrf('/phone/contacts/create');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/phone/contacts/create');
        }
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();

        $contactId = PhoneContact::create($data);
        ActivityLog::log('phone.contact_create', 'phone_contact', $contactId, "إضافة جهة اتصال: {$data['name']}");
        flash_set('success', 'تمت إضافة جهة الاتصال.');
        redirect('/phone/contacts');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $contact = $this->findEditable((int) $params['id'], $companyId);

        View::render('phone::contacts.form', [
            'pageTitle' => 'تعديل جهة اتصال',
            'contact' => $contact,
            'companyUsers' => PhoneContact::companyExtensionUsers($companyId),
            'canManagePublic' => $this->canManagePublic(),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $contact = $this->findEditable((int) $params['id'], $companyId);
        $this->verifyCsrf('/phone/contacts/' . $contact['id'] . '/edit');

        $data = $this->validated($companyId, $contact);
        if ($data === null) {
            redirect('/phone/contacts/' . $contact['id'] . '/edit');
        }

        PhoneContact::update($contact['id'], $data);
        ActivityLog::log('phone.contact_update', 'phone_contact', $contact['id'], "تعديل جهة اتصال: {$data['name']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/phone/contacts');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $contact = $this->findEditable((int) $params['id'], $companyId);
        $this->verifyCsrf('/phone/contacts');

        PhoneContact::delete($contact['id']);
        ActivityLog::log('phone.contact_delete', 'phone_contact', $contact['id'], "حذف جهة اتصال: {$contact['name']}");
        flash_set('success', 'تم حذف جهة الاتصال.');
        redirect('/phone/contacts');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('phone::contacts.no-company', ['pageTitle' => 'دليل جهات الاتصال']);
            exit;
        }
        return $companyId;
    }

    private function guardAccess(): void
    {
        if (!Permission::check('phone.view')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    /**
     * إدارة الدليل العام (إنشاء/تعديل/حذف جهات عامة) محصورة بمدير النظام/الشركة أو
     * صلاحية phone.manage_contacts تحديداً - وليست متاحة لأي موظف عادي.
     */
    private function canManagePublic(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('phone.manage_contacts');
    }

    private function findEditable(int $id, int $companyId): array
    {
        $contact = PhoneContact::find($id);
        if (!$contact || (int) $contact['company_id'] !== $companyId) {
            flash_set('error', 'جهة الاتصال غير موجودة.');
            redirect('/phone/contacts');
        }

        $isOwner = (int) $contact['created_by'] === Auth::id();
        $isAdmin = Auth::isSystemAdmin() || Auth::isCompanyAdmin();

        if ($contact['visibility'] === 'public') {
            if (!$this->canManagePublic()) {
                $this->forbidden();
            }
        } elseif (!$isOwner && !$isAdmin) {
            $this->forbidden();
        }

        return $contact;
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
        exit;
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function validated(int $companyId, ?array $existing = null): ?array
    {
        $type = Request::input('type', 'external');
        $visibility = Request::input('visibility', 'private');
        $notes = trim((string) Request::input('notes', ''));

        if (!in_array($type, ['internal', 'external'], true)) {
            $type = 'external';
        }
        if (!in_array($visibility, ['public', 'private'], true)) {
            $visibility = 'private';
        }
        if ($visibility === 'public' && !$this->canManagePublic()) {
            flash_set('error', 'لا تملك صلاحية إضافة جهات اتصال عامة للشركة.');
            return null;
        }

        if ($type === 'internal') {
            $linkedUserId = (int) Request::input('linked_user_id', 0);
            $companyUsers = PhoneContact::companyExtensionUsers($companyId);
            $match = null;
            foreach ($companyUsers as $u) {
                if ((int) $u['id'] === $linkedUserId) {
                    $match = $u;
                    break;
                }
            }
            if (!$match) {
                flash_set('error', 'يرجى اختيار موظف صالح لديه تحويلة مفعّلة.');
                return null;
            }

            return [
                'type' => 'internal',
                'linked_user_id' => $linkedUserId,
                'name' => $match['name'],
                'phone_number' => null,
                'notes' => $notes ?: null,
                'visibility' => $visibility,
            ];
        }

        $name = trim((string) Request::input('name', ''));
        $phoneNumber = trim((string) Request::input('phone_number', ''));
        if ($name === '' || $phoneNumber === '') {
            flash_set('error', 'يرجى إدخال اسم ورقم جهة الاتصال.');
            return null;
        }

        return [
            'type' => 'external',
            'linked_user_id' => null,
            'name' => $name,
            'phone_number' => $phoneNumber,
            'notes' => $notes ?: null,
            'visibility' => $visibility,
        ];
    }
}
