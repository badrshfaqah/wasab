<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\Workspace;

/**
 * أساس متحكمات CRM: سياق الشركة، وحارس الوصول للمساحة، والقدرات التفصيلية.
 * كل متحكم يمر من هنا فلا تتسرّب مساحة لمن ليس عضواً فيها.
 */
abstract class BaseCrmController
{
    protected function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('crm::no-company', ['pageTitle' => 'إدارة العلاقات']);
            exit;
        }
        if (!Permission::check('crm.view')) {
            $this->forbidden();
        }
        return $companyId;
    }

    /** يعيد [المساحة، عضويتي فيها] أو يمنع الوصول. */
    protected function requireWorkspace(int $workspaceId, int $companyId): array
    {
        $workspace = Workspace::find($workspaceId);
        if (!$workspace || (int) $workspace['company_id'] !== $companyId) {
            flash_set('error', 'المساحة غير موجودة.');
            redirect('/crm');
        }
        $membership = Workspace::membership($workspaceId, Auth::id());
        if (!$membership) {
            $this->forbidden();
        }
        return [$workspace, $membership];
    }

    protected function requireAbility(array $membership, string $ability): void
    {
        if (!Workspace::can($membership, $ability)) {
            $this->forbidden();
        }
    }

    protected function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
        exit;
    }

    protected function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    /** مستخدمو الشركة النشطون (لاختيار الأعضاء والمسؤولين). */
    protected function companyUsers(int $companyId): array
    {
        return \App\Core\Database::select(
            "SELECT id, name, email FROM users WHERE company_id = :c AND status = 'active' ORDER BY name",
            ['c' => $companyId]
        );
    }
}
