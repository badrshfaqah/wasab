<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use Modules\Crm\Models\Activity;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Workspace;

/** تسجيل الأنشطة والمتابعات وجهات الاتصال داخل مساحة. */
class ActivityController extends BaseCrmController
{
    /** تسجيل نشاط على جهة + متابعة اختيارية تُنشئ مهمة. */
    public function store(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'activities.create');
        [$organization, ] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id'];
        $this->verifyCsrf($back);

        $type = (string) Request::input('type', 'note');
        if (!array_key_exists($type, Activity::types())) {
            $type = 'note';
        }
        $body = trim((string) Request::input('body', ''));
        $subject = trim((string) Request::input('subject', ''));
        if ($body === '' && $subject === '') {
            flash_set('error', 'اكتب ما جرى في هذا النشاط.');
            redirect($back);
        }

        $occurredAt = trim((string) Request::input('occurred_at', ''));
        $nextAt = trim((string) Request::input('next_action_at', ''));
        $contactId = (int) Request::input('contact_id', 0) ?: null;
        if ($contactId) {
            $contact = Contact::find($contactId);
            if (!$contact || (int) $contact['organization_id'] !== (int) $organization['id']) {
                $contactId = null;
            }
        }

        $activityId = Activity::log([
            'workspace_id' => (int) $workspace['id'],
            'organization_id' => (int) $organization['id'],
            'contact_id' => $contactId,
            'type' => $type,
            'subject' => mb_substr($subject, 0, 200) ?: null,
            'body' => $body ?: null,
            'outcome' => mb_substr(trim((string) Request::input('outcome', '')), 0, 255) ?: null,
            'occurred_at' => $occurredAt !== '' ? date('Y-m-d H:i:s', strtotime($occurredAt)) : date('Y-m-d H:i:s'),
            'next_action_at' => $nextAt !== '' ? date('Y-m-d H:i:s', strtotime($nextAt)) : null,
            'next_action_note' => mb_substr(trim((string) Request::input('next_action_note', '')), 0, 255) ?: null,
        ], $workspace, $organization, (int) Request::input('next_action_owner', 0) ?: null);

        CrmLog::add((int) $workspace['id'], 'activity.create', 'organization', (int) $organization['id'],
            'تسجيل نشاط (' . Activity::typeLabel($type) . ')');
        flash_set('success', $nextAt !== ''
            ? 'سُجّل النشاط وأُنشئت مهمة متابعة في ' . date('Y-m-d', strtotime($nextAt)) . '.'
            : 'سُجّل النشاط.');
        redirect($back);
    }

    /** إنهاء متابعة معلّقة (وتُغلق مهمتها). */
    public function completeFollowUp(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'activities.create');
        $activity = Activity::find((int) $params['activityId']);
        if (!$activity || (int) $activity['workspace_id'] !== (int) $workspace['id']) {
            flash_set('error', 'النشاط غير موجود.');
            redirect('/crm/w/' . $workspace['id']);
        }
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $activity['organization_id'];
        $this->verifyCsrf($back);

        Activity::completeFollowUp((int) $activity['id']);
        CrmLog::add((int) $workspace['id'], 'activity.followup_done', 'organization', (int) $activity['organization_id'], 'إنهاء متابعة');
        flash_set('success', 'أُنهيت المتابعة.');
        redirect($back);
    }

    public function deleteActivity(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $activity = Activity::find((int) $params['activityId']);
        if (!$activity || (int) $activity['workspace_id'] !== (int) $workspace['id']) {
            redirect('/crm/w/' . $workspace['id']);
        }
        // يحذف صاحب النشاط أو مدير المساحة فقط
        if ((int) $activity['user_id'] !== Auth::id() && ($membership['role'] ?? '') !== 'manager') {
            $this->forbidden();
        }
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $activity['organization_id'];
        $this->verifyCsrf($back);

        Database::delete('crm_activities', 'id = :id', ['id' => $activity['id']]);
        CrmLog::add((int) $workspace['id'], 'activity.delete', 'organization', (int) $activity['organization_id'], 'حذف نشاط');
        flash_set('success', 'حُذف النشاط.');
        redirect($back);
    }

    // ---------------- جهات الاتصال ----------------

    public function storeContact(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'contacts.manage');
        [$organization, ] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id'];
        $this->verifyCsrf($back);

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الشخص مطلوب.');
            redirect($back);
        }

        $contactId = (int) Request::input('contact_id', 0);
        $data = [
            'name' => mb_substr($name, 0, 150),
            'job_title' => mb_substr(trim((string) Request::input('job_title', '')), 0, 150) ?: null,
            'department' => mb_substr(trim((string) Request::input('department', '')), 0, 150) ?: null,
            'mobile' => mb_substr(trim((string) Request::input('mobile', '')), 0, 50) ?: null,
            'phone' => mb_substr(trim((string) Request::input('phone', '')), 0, 50) ?: null,
            'email' => mb_substr(trim((string) Request::input('email', '')), 0, 150) ?: null,
            'linkedin' => mb_substr(trim((string) Request::input('linkedin', '')), 0, 255) ?: null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
            'status' => Request::input('status') === 'inactive' ? 'inactive' : 'active',
        ];

        if ($contactId) {
            $existing = Contact::find($contactId);
            if (!$existing || (int) $existing['organization_id'] !== (int) $organization['id']) {
                flash_set('error', 'الشخص غير موجود.');
                redirect($back);
            }
            Contact::update($contactId, $data);
            CrmLog::add((int) $workspace['id'], 'contact.update', 'organization', (int) $organization['id'], 'تعديل بيانات: ' . $data['name']);
            flash_set('success', 'حُدّثت بيانات الشخص.');
        } else {
            Contact::create($data + [
                'company_id' => $companyId,
                'organization_id' => (int) $organization['id'],
                'created_by' => Auth::id(),
            ]);
            CrmLog::add((int) $workspace['id'], 'contact.create', 'organization', (int) $organization['id'], 'إضافة شخص: ' . $data['name']);
            flash_set('success', 'أُضيف الشخص إلى الجهة.');
        }
        redirect($back);
    }

    public function deleteContact(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'contacts.manage');
        [$organization, ] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id'];
        $this->verifyCsrf($back);

        $contact = Contact::find((int) $params['contactId']);
        if ($contact && (int) $contact['organization_id'] === (int) $organization['id']) {
            Contact::delete((int) $contact['id']);
            CrmLog::add((int) $workspace['id'], 'contact.delete', 'organization', (int) $organization['id'], 'حذف شخص: ' . $contact['name']);
            flash_set('success', 'حُذف الشخص.');
        }
        redirect($back);
    }

    // ---------------------------------------------------------------

    /** يتحقق أن الجهة مرتبطة فعلاً بهذه المساحة قبل أي عملية عليها. */
    private function requireLinkedOrg(array $workspace, int $orgId, int $companyId): array
    {
        $organization = Organization::find($orgId);
        $relation = $organization ? Organization::relation((int) $workspace['id'], $orgId) : null;
        if (!$organization || (int) $organization['company_id'] !== $companyId || !$relation) {
            flash_set('error', 'الجهة غير مرتبطة بهذه المساحة.');
            redirect('/crm/w/' . $workspace['id']);
        }
        return [$organization, $relation];
    }
}
