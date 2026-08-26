<?php

namespace Modules\Crm\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Workspace;

/** تصنيفات المساحة ووسومها - لمن يملك «إدارة التصنيفات وإعدادات المساحة». */
class SettingsController extends BaseCrmController
{
    public function index(array $params): void
    {
        [$workspace, ] = $this->guard($params);

        View::render('crm::settings', [
            'pageTitle' => 'تصنيفات ووسوم — ' . $workspace['name'],
            'workspace' => $workspace,
            'categories' => Database::select(
                'SELECT c.*, (SELECT COUNT(*) FROM crm_org_categories oc WHERE oc.category_id = c.id) AS uses
                   FROM crm_categories c WHERE c.workspace_id = :w ORDER BY c.sort_order, c.name',
                ['w' => $workspace['id']]
            ),
            'tags' => Organization::workspaceTags((int) $workspace['id']),
        ]);
    }

    public function storeCategory(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $back = '/crm/w/' . $workspace['id'] . '/settings';
        $this->verifyCsrf($back);

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم التصنيف مطلوب.');
            redirect($back);
        }
        $color = (string) Request::input('color', '#6b7280');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }
        $max = Database::first('SELECT COALESCE(MAX(sort_order), 0) AS m FROM crm_categories WHERE workspace_id = :w', ['w' => $workspace['id']]);
        Database::insert('crm_categories', [
            'workspace_id' => (int) $workspace['id'],
            'name' => mb_substr($name, 0, 120),
            'color' => $color,
            'sort_order' => ((int) ($max['m'] ?? 0)) + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        CrmLog::add((int) $workspace['id'], 'category.create', 'workspace', (int) $workspace['id'], 'إضافة تصنيف: ' . $name);
        flash_set('success', 'أُضيف التصنيف.');
        redirect($back);
    }

    public function updateCategory(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $back = '/crm/w/' . $workspace['id'] . '/settings';
        $this->verifyCsrf($back);
        $category = $this->requireCategory($workspace, (int) $params['categoryId']);

        if (Request::input('action') === 'delete') {
            Database::delete('crm_categories', 'id = :id', ['id' => $category['id']]);
            CrmLog::add((int) $workspace['id'], 'category.delete', 'workspace', (int) $workspace['id'], 'حذف تصنيف: ' . $category['name']);
            flash_set('success', 'حُذف التصنيف (وأُزيل عن الجهات التي كانت تحمله).');
            redirect($back);
        }

        $name = trim((string) Request::input('name', ''));
        $color = (string) Request::input('color', $category['color']);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = $category['color'];
        }
        Database::update('crm_categories', [
            'name' => $name !== '' ? mb_substr($name, 0, 120) : $category['name'],
            'color' => $color,
        ], 'id = :id', ['id' => $category['id']]);
        flash_set('success', 'حُفظ التصنيف.');
        redirect($back);
    }

    public function deleteTag(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $back = '/crm/w/' . $workspace['id'] . '/settings';
        $this->verifyCsrf($back);

        $tag = Database::first('SELECT * FROM crm_tags WHERE id = :id AND workspace_id = :w', ['id' => (int) $params['tagId'], 'w' => $workspace['id']]);
        if ($tag) {
            Database::delete('crm_tags', 'id = :id', ['id' => $tag['id']]);
            CrmLog::add((int) $workspace['id'], 'tag.delete', 'workspace', (int) $workspace['id'], 'حذف وسم: ' . $tag['name']);
            flash_set('success', 'حُذف الوسم من المساحة.');
        }
        redirect($back);
    }

    /** إضافة/إزالة وسم على جهة داخل المساحة. */
    public function tagOrganization(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');

        $relation = Organization::relation((int) $workspace['id'], (int) $params['orgId']);
        if (!$relation) {
            flash_set('error', 'الجهة غير مرتبطة بهذه المساحة.');
            redirect('/crm/w/' . $workspace['id']);
        }
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $params['orgId'];
        $this->verifyCsrf($back);

        $removeId = (int) Request::input('remove_tag', 0);
        if ($removeId) {
            Organization::removeTag((int) $relation['id'], $removeId);
            flash_set('success', 'أُزيل الوسم.');
            redirect($back);
        }

        $names = preg_split('/[,،]/u', (string) Request::input('tags', '')) ?: [];
        $added = 0;
        foreach ($names as $name) {
            if (trim($name) !== '') {
                Organization::addTag((int) $workspace['id'], (int) $relation['id'], $name);
                $added++;
            }
        }
        flash_set($added ? 'success' : 'error', $added ? 'أُضيفت الوسوم.' : 'اكتب وسماً واحداً على الأقل.');
        redirect($back);
    }

    // ---------------------------------------------------------------

    private function guard(array $params): array
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');
        return [$workspace, $membership];
    }

    private function requireCategory(array $workspace, int $categoryId): array
    {
        $category = Database::first(
            'SELECT * FROM crm_categories WHERE id = :id AND workspace_id = :w',
            ['id' => $categoryId, 'w' => $workspace['id']]
        );
        if (!$category) {
            flash_set('error', 'التصنيف غير موجود.');
            redirect('/crm/w/' . $workspace['id'] . '/settings');
        }
        return $category;
    }
}
