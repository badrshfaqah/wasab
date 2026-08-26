<?php

namespace Modules\Contacts\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Contacts\Models\Directory;

class DirectoryController
{
    private const KINDS = ['شركة', 'مؤسسة', 'جهة حكومية', 'جمعية', 'جهة إعلامية', 'أخرى'];

    /** الدليل بتبويبين: الجهات والأفراد. */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $tab = Request::query('tab') === 'people' ? 'people' : 'orgs';
        $filters = array_filter([
            'q' => trim((string) Request::query('q', '')),
            'kind' => trim((string) Request::query('kind', '')),
            'city' => trim((string) Request::query('city', '')),
            'standalone' => Request::query('standalone') ? 1 : 0,
            'archived' => Request::query('archived') ? 1 : 0,
        ]);
        $page = max(1, (int) Request::query('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        View::render('contacts::index', [
            'pageTitle' => 'جهات الاتصال',
            'tab' => $tab,
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'kinds' => self::KINDS,
            'orgs' => $tab === 'orgs' ? Directory::organizations($companyId, $filters, $perPage, $offset) : [],
            'orgsTotal' => Directory::countOrganizations($companyId, $filters),
            'people' => $tab === 'people' ? Directory::persons($companyId, $filters, $perPage, $offset) : [],
            'peopleTotal' => Directory::countPersons($companyId, $filters),
            'canCreate' => Permission::check('contacts.create'),
            'hasLegacyClients' => ImportClientsController::legacyAvailable($companyId),
        ]);
    }

    // ---------------- الجهات ----------------

    public function createOrg(): void
    {
        $this->requireCompanyContext();
        $this->guard('contacts.create');
        View::render('contacts::org_form', [
            'pageTitle' => 'جهة جديدة',
            'org' => null,
            'kinds' => self::KINDS,
        ]);
    }

    /** إنشاء جهة - ومعها شخص تواصل إلزامي، فجهة بلا من نكلّمه سجلٌّ ميت. */
    public function storeOrg(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.create');
        $this->verifyCsrf('/contacts/orgs/create');

        $data = $this->validatedOrg($companyId);
        if ($data === null) {
            redirect('/contacts/orgs/create');
        }
        $personName = trim((string) Request::input('person_name', ''));
        if ($personName === '') {
            flash_set('error', 'أضف شخص التواصل مع الجهة — لا تُنشأ جهة بلا شخص نكلّمه.');
            redirect('/contacts/orgs/create');
        }

        $orgId = Directory::createOrg($data + ['company_id' => $companyId, 'created_by' => Auth::id()]);

        // الشخص: إن كان مسجّلاً بالدليل نربطه، وإلا أنشأناه
        $personId = (int) Request::input('person_id', 0);
        if ($personId) {
            $person = Directory::findPerson($personId);
            if (!$person || (int) $person['company_id'] !== $companyId) {
                $personId = 0;
            }
        }
        if (!$personId) {
            $personId = Directory::createPerson([
                'company_id' => $companyId,
                'full_name' => mb_substr($personName, 0, 150),
                'job_title' => mb_substr(trim((string) Request::input('person_job', '')), 0, 150) ?: null,
                'mobile' => mb_substr(trim((string) Request::input('person_mobile', '')), 0, 50) ?: null,
                'email' => mb_substr(trim((string) Request::input('person_email', '')), 0, 150) ?: null,
                'created_by' => Auth::id(),
            ]);
        }
        Directory::link($personId, $orgId, (string) Request::input('person_job', ''), null, true);

        ActivityLog::log('contacts.org.create', 'contacts_organization', $orgId, "إضافة جهة: {$data['name']}");
        flash_set('success', 'أُضيفت الجهة ومعها شخص التواصل.');
        redirect('/contacts/orgs/' . $orgId);
    }

    public function showOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $org = $this->requireOrg((int) $params['id'], $companyId);

        View::render('contacts::org_show', [
            'pageTitle' => $org['name'],
            'org' => $org,
            'people' => Directory::peopleOf((int) $org['id']),
            'linked' => Directory::linkedItems($companyId, 'organization', (int) $org['id']),
            'allPeople' => Directory::persons($companyId, [], 500),
            'kinds' => self::KINDS,
            'canEdit' => Permission::check('contacts.edit'),
            'canDelete' => Permission::check('contacts.delete'),
        ]);
    }

    public function updateOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.edit');
        $org = $this->requireOrg((int) $params['id'], $companyId);
        $back = '/contacts/orgs/' . $org['id'];
        $this->verifyCsrf($back);

        $data = $this->validatedOrg($companyId);
        if ($data === null) {
            redirect($back);
        }
        Directory::updateOrg((int) $org['id'], $data);
        flash_set('success', 'حُفظت بيانات الجهة.');
        redirect($back);
    }

    public function archiveOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.delete');
        $org = $this->requireOrg((int) $params['id'], $companyId);
        $this->verifyCsrf('/contacts/orgs/' . $org['id']);

        $status = $org['status'] === 'active' ? 'archived' : 'active';
        Directory::updateOrg((int) $org['id'], ['status' => $status]);
        flash_set('success', $status === 'archived' ? 'أُرشفت الجهة.' : 'أُعيد تنشيط الجهة.');
        redirect('/contacts/orgs/' . $org['id']);
    }

    // ---------------- الأفراد ----------------

    public function createPerson(): void
    {
        $this->requireCompanyContext();
        $this->guard('contacts.create');
        View::render('contacts::person_form', [
            'pageTitle' => 'فرد جديد',
            'person' => null,
        ]);
    }

    public function storePerson(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.create');
        $this->verifyCsrf('/contacts/people/create');

        $data = $this->validatedPerson();
        if ($data === null) {
            redirect('/contacts/people/create');
        }
        $personId = Directory::createPerson($data + ['company_id' => $companyId, 'created_by' => Auth::id()]);
        ActivityLog::log('contacts.person.create', 'contacts_person', $personId, "إضافة فرد: {$data['full_name']}");
        flash_set('success', 'أُضيف الفرد إلى الدليل — يمكنك ربطه بجهة أو أكثر من صفحته.');
        redirect('/contacts/people/' . $personId);
    }

    public function showPerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $person = $this->requirePerson((int) $params['id'], $companyId);

        View::render('contacts::person_show', [
            'pageTitle' => $person['full_name'],
            'person' => $person,
            'orgs' => Directory::orgsOf((int) $person['id']),
            'linked' => Directory::linkedItems($companyId, 'person', (int) $person['id']),
            'allOrgs' => Directory::organizations($companyId, [], 500),
            'canEdit' => Permission::check('contacts.edit'),
            'canDelete' => Permission::check('contacts.delete'),
        ]);
    }

    public function updatePerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.edit');
        $person = $this->requirePerson((int) $params['id'], $companyId);
        $back = '/contacts/people/' . $person['id'];
        $this->verifyCsrf($back);

        $data = $this->validatedPerson();
        if ($data === null) {
            redirect($back);
        }
        Directory::updatePerson((int) $person['id'], $data);
        flash_set('success', 'حُفظت بيانات الفرد.');
        redirect($back);
    }

    public function archivePerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.delete');
        $person = $this->requirePerson((int) $params['id'], $companyId);
        $this->verifyCsrf('/contacts/people/' . $person['id']);

        $status = $person['status'] === 'active' ? 'archived' : 'active';
        Directory::updatePerson((int) $person['id'], ['status' => $status]);
        flash_set('success', $status === 'archived' ? 'أُرشف الفرد.' : 'أُعيد تنشيطه.');
        redirect('/contacts/people/' . $person['id']);
    }

    // ---------------- الربط ----------------

    /** ربط فرد بجهة (من أي من الصفحتين) بمسمّى خاص بهذه العلاقة. */
    public function link(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.edit');
        $back = (string) Request::input('back', '/contacts');
        $this->verifyCsrf($back);

        $orgId = (int) Request::input('organization_id', 0);
        $personId = (int) Request::input('person_id', 0);
        $personName = trim((string) Request::input('person_name', ''));

        $org = Directory::findOrg($orgId);
        if (!$org || (int) $org['company_id'] !== $companyId) {
            flash_set('error', 'الجهة غير موجودة.');
            redirect($back);
        }
        // شخص جديد يُنشأ ويُربط في خطوة واحدة
        if (!$personId && $personName !== '') {
            $personId = Directory::createPerson([
                'company_id' => $companyId,
                'full_name' => mb_substr($personName, 0, 150),
                'mobile' => mb_substr(trim((string) Request::input('person_mobile', '')), 0, 50) ?: null,
                'email' => mb_substr(trim((string) Request::input('person_email', '')), 0, 150) ?: null,
                'created_by' => Auth::id(),
            ]);
        }
        $person = $personId ? Directory::findPerson($personId) : null;
        if (!$person || (int) $person['company_id'] !== $companyId) {
            flash_set('error', 'اختر فرداً صحيحاً أو اكتب اسمه.');
            redirect($back);
        }

        Directory::link(
            (int) $person['id'],
            (int) $org['id'],
            (string) Request::input('job_title', ''),
            (string) Request::input('department', ''),
            (bool) Request::input('is_primary')
        );
        flash_set('success', 'رُبط ' . $person['full_name'] . ' بـ ' . $org['name'] . '.');
        redirect($back);
    }

    public function unlink(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guard('contacts.edit');
        $back = (string) Request::input('back', '/contacts');
        $this->verifyCsrf($back);

        $org = Directory::findOrg((int) Request::input('organization_id', 0));
        $person = Directory::findPerson((int) Request::input('person_id', 0));
        if ($org && $person && (int) $org['company_id'] === $companyId && (int) $person['company_id'] === $companyId) {
            Directory::unlink((int) $person['id'], (int) $org['id']);
            flash_set('success', 'أُلغي الربط (ويبقى كلٌّ منهما في الدليل).');
        }
        redirect($back);
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('contacts::no-company', ['pageTitle' => 'جهات الاتصال']);
            exit;
        }
        $this->guard('contacts.view');
        return $companyId;
    }

    private function guard(string $permission): void
    {
        if (!Permission::check($permission)) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function requireOrg(int $id, int $companyId): array
    {
        $org = Directory::findOrg($id);
        if (!$org || (int) $org['company_id'] !== $companyId) {
            flash_set('error', 'الجهة غير موجودة.');
            redirect('/contacts');
        }
        return $org;
    }

    private function requirePerson(int $id, int $companyId): array
    {
        $person = Directory::findPerson($id);
        if (!$person || (int) $person['company_id'] !== $companyId) {
            flash_set('error', 'الفرد غير موجود.');
            redirect('/contacts?tab=people');
        }
        return $person;
    }

    private function validatedOrg(int $companyId): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الجهة مطلوب.');
            return null;
        }
        $social = [];
        foreach (['linkedin', 'twitter', 'instagram'] as $network) {
            $value = trim((string) Request::input('social_' . $network, ''));
            if ($value !== '') {
                $social[$network] = mb_substr($value, 0, 255);
            }
        }
        $data = [
            'name' => mb_substr($name, 0, 200),
            'trade_name' => mb_substr(trim((string) Request::input('trade_name', '')), 0, 200) ?: null,
            'kind' => in_array(Request::input('kind'), self::KINDS, true) ? Request::input('kind') : null,
            'sector' => mb_substr(trim((string) Request::input('sector', '')), 0, 120) ?: null,
            'country' => mb_substr(trim((string) Request::input('country', '')), 0, 80) ?: null,
            'city' => mb_substr(trim((string) Request::input('city', '')), 0, 80) ?: null,
            'address' => mb_substr(trim((string) Request::input('address', '')), 0, 255) ?: null,
            'website' => mb_substr(trim((string) Request::input('website', '')), 0, 200) ?: null,
            'email' => mb_substr(trim((string) Request::input('email', '')), 0, 150) ?: null,
            'phone' => mb_substr(trim((string) Request::input('phone', '')), 0, 50) ?: null,
            'social_json' => $social ? json_encode($social, JSON_UNESCAPED_UNICODE) : null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
        ];
        $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads/contacts/' . $companyId);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            return null;
        }
        if ($upload['filename']) {
            $data['logo'] = $upload['filename'];
        }
        return $data;
    }

    private function validatedPerson(): ?array
    {
        $name = trim((string) Request::input('full_name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الفرد مطلوب.');
            return null;
        }
        return [
            'full_name' => mb_substr($name, 0, 150),
            'job_title' => mb_substr(trim((string) Request::input('job_title', '')), 0, 150) ?: null,
            'mobile' => mb_substr(trim((string) Request::input('mobile', '')), 0, 50) ?: null,
            'phone' => mb_substr(trim((string) Request::input('phone', '')), 0, 50) ?: null,
            'email' => mb_substr(trim((string) Request::input('email', '')), 0, 150) ?: null,
            'linkedin' => mb_substr(trim((string) Request::input('linkedin', '')), 0, 255) ?: null,
            'city' => mb_substr(trim((string) Request::input('city', '')), 0, 80) ?: null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
        ];
    }
}
