<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\CrmList;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Workspace;

/** قوائم الجهات داخل المساحة - الجهة تظهر في عدة قوائم دون تكرار بياناتها. */
class ListController extends BaseCrmController
{
    public function index(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        View::render('crm::lists.index', [
            'pageTitle' => 'القوائم — ' . $workspace['name'],
            'workspace' => $workspace,
            'lists' => CrmList::forWorkspace((int) $workspace['id']),
            'canManage' => Workspace::can($membership, 'orgs.edit'),
        ]);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $list = $this->requireList($workspace, (int) $params['listId']);

        View::render('crm::lists.show', [
            'pageTitle' => $list['name'],
            'workspace' => $workspace,
            'list' => $list,
            'rows' => CrmList::organizations((int) $list['id']),
            'allOrgs' => Organization::inWorkspace((int) $workspace['id'], [], 500),
            'canManage' => Workspace::can($membership, 'orgs.edit'),
        ]);
    }

    public function store(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');
        $back = '/crm/w/' . $workspace['id'] . '/lists';
        $this->verifyCsrf($back);

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم القائمة مطلوب.');
            redirect($back);
        }
        $listId = CrmList::create((int) $workspace['id'], $name, trim((string) Request::input('description', '')) ?: null, Auth::id());
        CrmLog::add((int) $workspace['id'], 'list.create', 'workspace', (int) $workspace['id'], 'إنشاء قائمة: ' . $name);
        flash_set('success', 'أُنشئت القائمة.');
        redirect('/crm/w/' . $workspace['id'] . '/lists/' . $listId);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');
        $list = $this->requireList($workspace, (int) $params['listId']);
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/lists');

        CrmList::delete((int) $list['id']);
        CrmLog::add((int) $workspace['id'], 'list.delete', 'workspace', (int) $workspace['id'], 'حذف قائمة: ' . $list['name']);
        flash_set('success', 'حُذفت القائمة (والجهات نفسها لم تتأثر).');
        redirect('/crm/w/' . $workspace['id'] . '/lists');
    }

    /** إضافة/إزالة جهة من قائمة. */
    public function items(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');
        $list = $this->requireList($workspace, (int) $params['listId']);
        $back = '/crm/w/' . $workspace['id'] . '/lists/' . $list['id'];
        $this->verifyCsrf($back);

        $orgId = (int) Request::input('organization_id', 0);
        $relation = Organization::relation((int) $workspace['id'], $orgId);
        if (!$relation) {
            flash_set('error', 'الجهة غير مرتبطة بهذه المساحة.');
            redirect($back);
        }

        if (Request::input('action') === 'remove') {
            CrmList::removeItem((int) $list['id'], (int) $relation['id']);
            flash_set('success', 'أُزيلت الجهة من القائمة.');
        } else {
            CrmList::addItem((int) $list['id'], (int) $relation['id']);
            flash_set('success', 'أُضيفت الجهة إلى القائمة.');
        }
        redirect($back);
    }

    private function requireList(array $workspace, int $listId): array
    {
        $list = CrmList::find($listId);
        if (!$list || (int) $list['workspace_id'] !== (int) $workspace['id']) {
            flash_set('error', 'القائمة غير موجودة.');
            redirect('/crm/w/' . $workspace['id'] . '/lists');
        }
        return $list;
    }
}
