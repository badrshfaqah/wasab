<?php

namespace Modules\Assets\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Assets\Models\Asset;
use Modules\Assets\Models\AssetHandover;
use Modules\Assets\Models\AssetHolder;
use Modules\Assets\Models\AssetLog;

/**
 * إسناد وإرجاع العهد عبر محاضر التسليم. الحامل بثلاثة مصادر (ملف وظيفي / مستخدم /
 * يدوي) عبر AssetHolder، والمحضر قد يضم عدة أصول لنفس الحامل دفعة واحدة.
 */
class AssetHandoverController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.view')) {
            $this->forbidden();
            return;
        }
        $page = max(1, (int) Request::query('page', 1));
        $result = AssetHandover::paginate($companyId, $page, 20);

        View::render('assets::handovers.index', [
            'pageTitle' => 'محاضر التسليم',
            'handovers' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'canAssign' => $this->can('assets.assign'),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.assign')) {
            $this->forbidden();
            return;
        }
        $selectable = AssetHolder::selectable($companyId);

        View::render('assets::handovers.form', [
            'pageTitle' => 'محضر تسليم جديد',
            'assignable' => Asset::assignable($companyId),
            'employees' => $selectable['employees'],
            'users' => $selectable['users'],
            'preselectAsset' => (int) Request::query('asset', 0),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.assign')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/custody/handovers/create');

        // الحامل: نوع + مرجع (أو اسم يدوي) - يُتحقق من انتمائه للشركة
        $holderType = Request::input('holder_type', 'manual');
        if (!in_array($holderType, ['employee', 'user', 'manual'], true)) {
            $holderType = 'manual';
        }
        $holderRef = $holderType === 'manual' ? null : ((int) Request::input('holder_ref', 0) ?: null);
        $manualName = (string) Request::input('holder_manual_name', '');
        $holderName = AssetHolder::resolveName($companyId, $holderType, $holderRef, $manualName);
        if ($holderName === null) {
            flash_set('error', 'حدّد حامل العهدة (اختر موظفاً/مستخدماً أو اكتب اسماً يدوياً صحيحاً).');
            redirect('/custody/handovers/create');
        }

        // الأصول: يجب أن تكون متاحة وتخصّ الشركة
        $assetIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['asset_ids'] ?? [])), fn ($id) => $id > 0)));
        if (!$assetIds) {
            flash_set('error', 'اختر أصلاً واحداً على الأقل للتسليم.');
            redirect('/custody/handovers/create');
        }
        $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
        $validAssets = Database::select(
            "SELECT id, name FROM assets_assets
              WHERE id IN ({$placeholders}) AND company_id = ? AND status = 'available'",
            array_merge($assetIds, [$companyId])
        );
        if (count($validAssets) !== count($assetIds)) {
            flash_set('error', 'بعض الأصول المختارة لم تعد متاحة للتسليم، حدّث الصفحة وأعد المحاولة.');
            redirect('/custody/handovers/create');
        }

        $handoverDate = Request::input('handover_date') ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $handoverId = AssetHandover::create([
            'company_id' => $companyId,
            'holder_type' => $holderType,
            'holder_ref' => $holderRef,
            'holder_name' => $holderName,
            'holder_contact' => mb_substr(trim((string) Request::input('holder_contact', '')), 0, 120) ?: null,
            'handover_date' => $handoverDate,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
            'created_by' => Auth::id(),
            'created_at' => $now,
        ]);

        foreach ($validAssets as $a) {
            AssetHandover::addItem($handoverId, (int) $a['id']);
            Asset::update((int) $a['id'], [
                'status' => 'assigned',
                'current_holder_type' => $holderType,
                'current_holder_ref' => $holderRef,
                'current_holder_name' => $holderName,
                'assigned_at' => $now,
                'updated_at' => $now,
            ]);
            AssetLog::add((int) $a['id'], Auth::id(), 'assigned', "تسليم عهدة إلى: {$holderName}");
        }

        // تنبيه الحامل إن كان مربوطاً بحساب بالنظام
        $notifyUser = AssetHolder::linkedUserId($companyId, $holderType, $holderRef);
        if ($notifyUser) {
            $count = count($validAssets);
            Notification::send(
                $notifyUser,
                '📦 عهدة جديدة باسمك',
                "تم تسجيل {$count} " . ($count === 1 ? 'أصل' : 'أصول') . ' في عهدتك.',
                route('/custody/my')
            );
        }

        ActivityLog::log('assets.handover', 'asset_handover', $handoverId, "محضر تسليم لـ {$holderName} (" . count($validAssets) . ' أصل)');
        flash_set('success', 'تم تسجيل محضر التسليم وإسناد العهد.');
        redirect('/custody/handovers/' . $handoverId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.view')) {
            $this->forbidden();
            return;
        }
        $handover = $this->findVisible((int) $params['id'], $companyId);

        View::render('assets::handovers.show', [
            'pageTitle' => 'محضر تسليم - ' . $handover['holder_name'],
            'handover' => $handover,
            'items' => AssetHandover::items((int) $handover['id']),
            'canAssign' => $this->can('assets.assign'),
        ]);
    }

    /** إرجاع بند (أصل) من محضر: يُعيد الأصل متاحاً ويسجّل حالة الإرجاع. */
    public function returnItem(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.assign')) {
            $this->forbidden();
            return;
        }
        $item = AssetHandover::findItem((int) $params['itemId']);
        if (!$item || (int) $item['company_id'] !== $companyId) {
            flash_set('error', 'البند غير موجود.');
            redirect('/custody/handovers');
        }
        $this->verifyCsrf('/custody/handovers/' . $item['handover_id']);

        if ($item['returned_at']) {
            flash_set('error', 'هذا الأصل مُرجع بالفعل.');
            redirect('/custody/handovers/' . $item['handover_id']);
        }

        $condition = mb_substr(trim((string) Request::input('return_condition', '')), 0, 60) ?: null;
        $note = mb_substr(trim((string) Request::input('return_note', '')), 0, 255) ?: null;

        AssetHandover::markReturned((int) $item['id'], $condition, $note);
        Asset::update((int) $item['asset_id'], [
            'status' => 'available',
            'current_holder_type' => null,
            'current_holder_ref' => null,
            'current_holder_name' => null,
            'assigned_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AssetLog::add((int) $item['asset_id'], Auth::id(), 'returned', 'إرجاع العهدة' . ($condition ? " (الحالة: {$condition})" : ''));

        ActivityLog::log('assets.return', 'asset', (int) $item['asset_id'], "إرجاع عهدة: {$item['asset_name']}");
        flash_set('success', 'تم تسجيل إرجاع الأصل.');
        redirect('/custody/handovers/' . $item['handover_id']);
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('assets::no-company', ['pageTitle' => 'العهد والأصول']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return Permission::check($key);
    }

    private function findVisible(int $id, int $companyId): array
    {
        $handover = AssetHandover::find($id);
        if (!$handover || (int) $handover['company_id'] !== $companyId) {
            flash_set('error', 'المحضر غير موجود.');
            redirect('/custody/handovers');
        }
        return $handover;
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
    }
}
