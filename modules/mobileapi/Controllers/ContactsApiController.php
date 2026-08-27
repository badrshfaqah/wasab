<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Permission;
use Modules\Contacts\Models\Directory;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON لدليل جهات الاتصال - نفس قواعد DirectoryController الويب.
 *
 * الدليل كيان مشترك: CRM وغيره يستهلكونه ولا يملكون جهاتهم. لذلك كل ما
 * يخصّ الجهات والأفراد يمر من هنا حصراً.
 */
class ContactsApiController
{
    /** نفس قائمة DirectoryController::KINDS - القيمة هي التسمية. */
    private const KINDS = ['شركة', 'مؤسسة', 'جهة حكومية', 'جمعية', 'جهة إعلامية', 'أخرى'];

    private const PER_PAGE = 25;

    // ---------- القوائم ----------

    /** GET /api/v1/contacts/orgs?q=&kind=&city=&archived=&page= */
    public function organizations(): void
    {
        $companyId = $this->requireCompanyContext();

        $filters = [];
        foreach (['q' => 'q', 'kind' => 'kind', 'city' => 'city'] as $key => $input) {
            $value = trim((string) Api::input($input, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }
        // الموديل يفهم archived كـ"أظهر المؤرشف أيضاً" لا "المؤرشف فقط".
        if (filter_var(Api::input('archived', false), FILTER_VALIDATE_BOOLEAN)) {
            $filters['archived'] = 1;
        }

        $page = max(1, (int) Api::input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        Api::ok([
            'organizations' => array_map([$this, 'orgSummary'], Directory::organizations($companyId, $filters, self::PER_PAGE, $offset)),
            'total' => Directory::countOrganizations($companyId, $filters),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'kinds' => self::KINDS,
        ] + $this->capabilities());
    }

    /** GET /api/v1/contacts/people?q=&org=&standalone=&archived=&page= */
    public function persons(): void
    {
        $companyId = $this->requireCompanyContext();

        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $org = (int) Api::input('org', 0);
        if ($org > 0) {
            $filters['org'] = $org;
        }
        if (filter_var(Api::input('standalone', false), FILTER_VALIDATE_BOOLEAN)) {
            $filters['standalone'] = 1;
        }
        if (filter_var(Api::input('archived', false), FILTER_VALIDATE_BOOLEAN)) {
            $filters['archived'] = 1;
        }

        $page = max(1, (int) Api::input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        Api::ok([
            'people' => array_map([$this, 'personSummary'], Directory::persons($companyId, $filters, self::PER_PAGE, $offset)),
            'total' => Directory::countPersons($companyId, $filters),
            'page' => $page,
            'per_page' => self::PER_PAGE,
        ] + $this->capabilities());
    }

    // ---------- الملفات ----------

    /** GET /api/v1/contacts/orgs/{id} - ملف الجهة: بياناتها وأفرادها وما ارتبط بها. */
    public function showOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $org = $this->requireOrg((int) $params['id'], $companyId);

        Api::ok([
            'organization' => $this->orgDetail($org),
            'people' => array_map([$this, 'linkedPersonPayload'], Directory::peopleOf((int) $org['id'])),
            'linked' => $this->linkedPayload(Directory::linkedItems($companyId, 'organization', (int) $org['id'])),
            'kinds' => self::KINDS,
        ] + $this->capabilities());
    }

    /** GET /api/v1/contacts/people/{id} - ملف الفرد: بياناته وجهاته وما ارتبط به. */
    public function showPerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $person = $this->requirePerson((int) $params['id'], $companyId);

        Api::ok([
            'person' => $this->personDetail($person),
            'organizations' => array_map([$this, 'linkedOrgPayload'], Directory::orgsOf((int) $person['id'])),
            'linked' => $this->linkedPayload(Directory::linkedItems($companyId, 'person', (int) $person['id'])),
        ] + $this->capabilities());
    }

    // ---------- الإنشاء والتعديل: الجهات ----------

    /**
     * POST /api/v1/contacts/orgs
     * القاعدة الأساسية: لا تُنشأ جهة بلا شخص تواصل واحد على الأقل.
     * يُقبل إما person_id لفرد قائم، أو person_name لإنشائه معها.
     */
    public function storeOrg(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.create');

        $data = $this->validatedOrg();

        $personId = (int) Api::input('person_id', 0);
        $personName = trim((string) Api::input('person_name', ''));

        if ($personId > 0) {
            $existing = Directory::findPerson($personId);
            if (!$existing || (int) $existing['company_id'] !== $companyId) {
                $personId = 0;
            }
        }
        if ($personId === 0 && $personName === '') {
            Api::error('أضف شخص التواصل مع الجهة — لا تُنشأ جهة بلا شخص نكلّمه.', 422, 'contact_person_required');
        }

        $orgId = Directory::createOrg($data + [
            'company_id' => $companyId,
            'created_by' => Auth::id(),
        ]);

        $personJob = mb_substr(trim((string) Api::input('person_job', '')), 0, 150);
        if ($personId === 0) {
            $personId = Directory::createPerson([
                'company_id' => $companyId,
                'full_name' => mb_substr($personName, 0, 150),
                'job_title' => $personJob ?: null,
                'mobile' => mb_substr(trim((string) Api::input('person_mobile', '')), 0, 50) ?: null,
                'email' => mb_substr(trim((string) Api::input('person_email', '')), 0, 150) ?: null,
                'created_by' => Auth::id(),
            ]);
        }

        // شخص التواصل الأول هو الرئيسي دائماً - كما في الويب.
        Directory::link($personId, $orgId, $personJob, null, true);
        ActivityLog::log('contacts.org.create', 'contacts_organization', (string) $orgId, 'إضافة جهة من الجوال: ' . $data['name']);

        Api::ok(['id' => $orgId, 'person_id' => $personId, 'message' => 'أُضيفت الجهة ومعها شخص التواصل.'], 201);
    }

    /** POST /api/v1/contacts/orgs/{id} */
    public function updateOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.edit');
        $org = $this->requireOrg((int) $params['id'], $companyId);

        Directory::updateOrg((int) $org['id'], $this->validatedOrg());
        Api::ok(['id' => (int) $org['id'], 'message' => 'حُفظت بيانات الجهة.']);
    }

    /** POST /api/v1/contacts/orgs/{id}/archive - تبديل: أرشفة/تنشيط. */
    public function archiveOrg(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.delete');
        $org = $this->requireOrg((int) $params['id'], $companyId);

        $status = $org['status'] === 'active' ? 'archived' : 'active';
        Directory::updateOrg((int) $org['id'], ['status' => $status]);

        Api::ok([
            'status' => $status,
            'message' => $status === 'archived' ? 'أُرشفت الجهة.' : 'أُعيد تنشيط الجهة.',
        ]);
    }

    // ---------- الإنشاء والتعديل: الأفراد ----------

    /** POST /api/v1/contacts/people - الفرد قد يبقى مستقلاً بلا جهة. */
    public function storePerson(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.create');

        $data = $this->validatedPerson();
        $personId = Directory::createPerson($data + [
            'company_id' => $companyId,
            'created_by' => Auth::id(),
        ]);
        ActivityLog::log('contacts.person.create', 'contacts_person', (string) $personId, 'إضافة فرد من الجوال: ' . $data['full_name']);

        Api::ok(['id' => $personId, 'message' => 'أُضيف الفرد إلى الدليل — يمكنك ربطه بجهة أو أكثر من صفحته.'], 201);
    }

    /** POST /api/v1/contacts/people/{id} */
    public function updatePerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.edit');
        $person = $this->requirePerson((int) $params['id'], $companyId);

        Directory::updatePerson((int) $person['id'], $this->validatedPerson());
        Api::ok(['id' => (int) $person['id'], 'message' => 'حُفظت بيانات الفرد.']);
    }

    /** POST /api/v1/contacts/people/{id}/archive - تبديل. */
    public function archivePerson(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.delete');
        $person = $this->requirePerson((int) $params['id'], $companyId);

        $status = $person['status'] === 'active' ? 'archived' : 'active';
        Directory::updatePerson((int) $person['id'], ['status' => $status]);

        Api::ok([
            'status' => $status,
            'message' => $status === 'archived' ? 'أُرشف الفرد.' : 'أُعيد تنشيطه.',
        ]);
    }

    // ---------- الربط ----------

    /**
     * POST /api/v1/contacts/link  {organization_id, person_id|person_name, job_title?, department?, is_primary?}
     * المسمّى صفة للعلاقة لا للفرد: يُحفظ على الرابط نفسه.
     */
    public function link(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.edit');

        $org = $this->requireOrg((int) Api::input('organization_id', 0), $companyId);

        $personId = (int) Api::input('person_id', 0);
        $personName = trim((string) Api::input('person_name', ''));
        if ($personId === 0 && $personName !== '') {
            $personId = Directory::createPerson([
                'company_id' => $companyId,
                'full_name' => mb_substr($personName, 0, 150),
                'mobile' => mb_substr(trim((string) Api::input('person_mobile', '')), 0, 50) ?: null,
                'email' => mb_substr(trim((string) Api::input('person_email', '')), 0, 150) ?: null,
                'created_by' => Auth::id(),
            ]);
        }

        $person = $personId > 0 ? Directory::findPerson($personId) : null;
        if (!$person || (int) $person['company_id'] !== $companyId) {
            Api::error('اختر فرداً صحيحاً أو اكتب اسمه.', 422, 'validation');
        }

        Directory::link(
            $personId,
            (int) $org['id'],
            mb_substr(trim((string) Api::input('job_title', '')), 0, 150),
            mb_substr(trim((string) Api::input('department', '')), 0, 150),
            filter_var(Api::input('is_primary', false), FILTER_VALIDATE_BOOLEAN)
        );

        Api::ok([
            'person_id' => $personId,
            'message' => 'رُبط ' . $person['full_name'] . ' بـ ' . $org['name'] . '.',
        ], 201);
    }

    /** POST /api/v1/contacts/unlink  {organization_id, person_id} - يبقى كلٌّ منهما في الدليل. */
    public function unlink(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requirePermission('contacts.edit');

        $org = $this->requireOrg((int) Api::input('organization_id', 0), $companyId);
        $person = $this->requirePerson((int) Api::input('person_id', 0), $companyId);

        Directory::unlink((int) $person['id'], (int) $org['id']);
        Api::ok(['message' => 'أُلغي الربط (ويبقى كلٌّ منهما في الدليل).']);
    }

    // ---------- تعريف المتصل ----------

    /**
     * GET /api/v1/contacts/resolve?number= - يعرّف رقماً واردًا من الدليل.
     * المطابقة بآخر تسعة أرقام فتتجاوز مفتاح الدولة وصفر البداية.
     */
    public function resolveNumber(): void
    {
        $companyId = $this->requireCompanyContext();

        $raw = trim((string) Api::input('number', ''));
        if ($raw === '') {
            Api::error('أرسل الرقم المراد تعريفه.', 422, 'validation');
        }

        $match = Directory::resolveByNumber($companyId, $raw);
        if (!$match) {
            Api::ok(['match' => null]);
        }

        Api::ok(['match' => [
            'type' => $match['type'],
            'id' => (int) $match['id'],
            'name' => $match['name'],
            'path' => $match['type'] === 'person'
                ? '/contacts/people/' . $match['id']
                : '/contacts/orgs/' . $match['id'],
        ]]);
    }

    // ---------- نفس قواعد DirectoryController ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        Api::requirePermission('contacts.view');
        return $companyId;
    }

    private function requirePermission(string $key): void
    {
        Api::requirePermission($key);
    }

    /** findOrg غير مقيّد بالشركة في الموديل - نقيّده هنا دائماً. */
    private function requireOrg(int $id, int $companyId): array
    {
        $org = $id > 0 ? Directory::findOrg($id) : null;
        if (!$org || (int) $org['company_id'] !== $companyId) {
            Api::error('الجهة غير موجودة.', 404, 'not_found');
        }
        return $org;
    }

    private function requirePerson(int $id, int $companyId): array
    {
        $person = $id > 0 ? Directory::findPerson($id) : null;
        if (!$person || (int) $person['company_id'] !== $companyId) {
            Api::error('الفرد غير موجود.', 404, 'not_found');
        }
        return $person;
    }

    private function capabilities(): array
    {
        return [
            'can_create' => Permission::check('contacts.create'),
            'can_edit' => Permission::check('contacts.edit'),
            'can_delete' => Permission::check('contacts.delete'),
        ];
    }

    /** نفس عقد validatedOrg في الويب (بلا الشعار - لا مسار يخدمه للجوال بعد). */
    private function validatedOrg(): array
    {
        $name = trim((string) Api::input('name', ''));
        if ($name === '') {
            Api::error('اسم الجهة مطلوب.', 422, 'validation');
        }

        $kind = (string) Api::input('kind', '');
        $social = [];
        foreach (['linkedin', 'twitter', 'instagram'] as $network) {
            $value = mb_substr(trim((string) Api::input('social_' . $network, '')), 0, 255);
            if ($value !== '') {
                $social[$network] = $value;
            }
        }

        return [
            'name' => mb_substr($name, 0, 200),
            'trade_name' => mb_substr(trim((string) Api::input('trade_name', '')), 0, 200) ?: null,
            'kind' => in_array($kind, self::KINDS, true) ? $kind : null,
            'sector' => mb_substr(trim((string) Api::input('sector', '')), 0, 120) ?: null,
            'country' => mb_substr(trim((string) Api::input('country', '')), 0, 80) ?: null,
            'city' => mb_substr(trim((string) Api::input('city', '')), 0, 80) ?: null,
            'address' => mb_substr(trim((string) Api::input('address', '')), 0, 255) ?: null,
            'website' => mb_substr(trim((string) Api::input('website', '')), 0, 200) ?: null,
            'email' => mb_substr(trim((string) Api::input('email', '')), 0, 150) ?: null,
            'phone' => mb_substr(trim((string) Api::input('phone', '')), 0, 50) ?: null,
            'social_json' => $social ? json_encode($social, JSON_UNESCAPED_UNICODE) : null,
            'notes' => trim((string) Api::input('notes', '')) ?: null,
        ];
    }

    private function validatedPerson(): array
    {
        $fullName = trim((string) Api::input('full_name', ''));
        if ($fullName === '') {
            Api::error('اسم الفرد مطلوب.', 422, 'validation');
        }

        return [
            'full_name' => mb_substr($fullName, 0, 150),
            'job_title' => mb_substr(trim((string) Api::input('job_title', '')), 0, 150) ?: null,
            'mobile' => mb_substr(trim((string) Api::input('mobile', '')), 0, 50) ?: null,
            'phone' => mb_substr(trim((string) Api::input('phone', '')), 0, 50) ?: null,
            'email' => mb_substr(trim((string) Api::input('email', '')), 0, 150) ?: null,
            'linkedin' => mb_substr(trim((string) Api::input('linkedin', '')), 0, 255) ?: null,
            'city' => mb_substr(trim((string) Api::input('city', '')), 0, 80) ?: null,
            'notes' => trim((string) Api::input('notes', '')) ?: null,
        ];
    }

    // ---------- التسلسل ----------

    private function orgSummary(array $o): array
    {
        return [
            'id' => (int) $o['id'],
            'name' => $o['name'],
            'trade_name' => $o['trade_name'] ?? null,
            'kind' => $o['kind'] ?? null,
            'city' => $o['city'] ?? null,
            'phone' => $o['phone'] ?? null,
            'status' => $o['status'],
            'people_count' => isset($o['people_count']) ? (int) $o['people_count'] : 0,
            'primary_person' => $o['primary_person'] ?? null,
        ];
    }

    private function orgDetail(array $o): array
    {
        $social = [];
        if (!empty($o['social_json'])) {
            $decoded = json_decode((string) $o['social_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $network => $value) {
                    $social[] = ['network' => (string) $network, 'value' => (string) $value];
                }
            }
        }

        return $this->orgSummary($o) + [
            'sector' => $o['sector'] ?? null,
            'country' => $o['country'] ?? null,
            'address' => $o['address'] ?? null,
            'website' => $o['website'] ?? null,
            'email' => $o['email'] ?? null,
            'notes' => $o['notes'] ?? null,
            'social' => $social,
            'created_at' => $o['created_at'] ?? null,
            'updated_at' => $o['updated_at'] ?? null,
        ];
    }

    private function personSummary(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'full_name' => $p['full_name'],
            'job_title' => $p['job_title'] ?? null,
            'mobile' => $p['mobile'] ?? null,
            'email' => $p['email'] ?? null,
            'city' => $p['city'] ?? null,
            'status' => $p['status'],
            'orgs_count' => isset($p['orgs_count']) ? (int) $p['orgs_count'] : 0,
            'main_org' => $p['main_org'] ?? null,
        ];
    }

    private function personDetail(array $p): array
    {
        return $this->personSummary($p) + [
            'phone' => $p['phone'] ?? null,
            'linkedin' => $p['linkedin'] ?? null,
            'notes' => $p['notes'] ?? null,
            'created_at' => $p['created_at'] ?? null,
            'updated_at' => $p['updated_at'] ?? null,
        ];
    }

    /** فرد داخل ملف جهة: المسمّى هنا صفة العلاقة لا الفرد. */
    private function linkedPersonPayload(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'full_name' => $p['full_name'],
            'role_title' => $p['role_title'] ?? null,
            'role_department' => $p['role_department'] ?? null,
            'is_primary' => (bool) ($p['is_primary'] ?? false),
            'mobile' => $p['mobile'] ?? null,
            'email' => $p['email'] ?? null,
            'status' => $p['status'] ?? 'active',
        ];
    }

    /** جهة داخل ملف فرد. */
    private function linkedOrgPayload(array $o): array
    {
        return [
            'id' => (int) $o['id'],
            'name' => $o['name'],
            'kind' => $o['kind'] ?? null,
            'city' => $o['city'] ?? null,
            'role_title' => $o['role_title'] ?? null,
            'role_department' => $o['role_department'] ?? null,
            'is_primary' => (bool) ($o['is_primary'] ?? false),
            'status' => $o['status'] ?? 'active',
        ];
    }

    /** ما ارتبط بالجهة/الفرد من وحدات أخرى - بمسارات نسبية يفهمها التطبيق. */
    private function linkedPayload(array $linked): array
    {
        return [
            'tasks' => array_map(fn ($t) => [
                'id' => (int) $t['id'],
                'title' => $t['title'],
                'status' => $t['status'],
                'due_date' => $t['due_date'] ?? null,
            ], $linked['tasks'] ?? []),
            'files' => array_map(fn ($f) => [
                'id' => (int) $f['id'],
                'title' => $f['title'],
                'created_at' => $f['created_at'] ?? null,
            ], $linked['files'] ?? []),
            'crm' => array_map(fn ($c) => [
                'workspace_id' => (int) $c['workspace_id'],
                'workspace_name' => $c['workspace_name'] ?? '',
                'icon' => $c['icon'] ?? null,
                'last_activity_at' => $c['last_activity_at'] ?? null,
                'next_action_at' => $c['next_action_at'] ?? null,
            ], $linked['crm'] ?? []),
        ];
    }
}
