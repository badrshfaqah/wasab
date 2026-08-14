<?php
/** @var array $requests @var ?array $own @var bool $canManage */
$stBadge = ['pending' => 'warning', 'done' => 'success', 'rejected' => 'danger'];
$stLabel = ['pending' => 'بالانتظار', 'done' => 'صدر الخطاب', 'rejected' => 'مرفوض'];
?>
<div class="page-head">
    <div><h1>طلبات الخطابات</h1><p><?= $canManage ? 'طلبات الموظفين — الاعتماد يولّد الخطاب تلقائياً بقالبه وختمه.' : 'اطلب خطابك (تعريف راتب/عمل...) ويصلك إشعار عند صدوره.' ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/forms') ?>">← النماذج</a>
        <?php if ($own): ?><a class="btn" href="<?= route('/forms/requests/new') ?>">+ طلب خطاب</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr>
            <?php if ($canManage): ?><th>الموظف</th><?php endif; ?>
            <th>الخطاب المطلوب</th><th>ملاحظة</th><th>الحالة</th><th style="text-align:end;"><?= $canManage ? 'القرار' : '' ?></th>
        </tr></thead>
        <tbody>
        <?php if (!$requests): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📨</div><h3>لا طلبات بعد</h3><p><?= $own ? 'قدّم أول طلب من زر «طلب خطاب».' : '' ?></p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
            <tr>
                <?php if ($canManage): ?><td><strong><?= e($r['full_name']) ?></strong></td><?php endif; ?>
                <td><?= e($r['template_name']) ?></td>
                <td style="max-width:220px;"><?= e($r['note'] ?: '—') ?></td>
                <td>
                    <span class="badge badge-<?= $stBadge[$r['status']] ?? 'muted' ?>"><?= e($stLabel[$r['status']] ?? $r['status']) ?></span>
                    <?php if ($r['status'] === 'done' && $r['letter_id']): ?>
                        <a class="hint" href="<?= route('/forms/' . $r['letter_id']) ?>">عرض الخطاب</a>
                    <?php elseif ($r['status'] === 'rejected' && $r['decision_note']): ?>
                        <div class="hint"><?= e($r['decision_note']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align:end;white-space:nowrap;">
                    <?php if ($canManage && $r['status'] === 'pending'): ?>
                        <form method="post" action="<?= route('/forms/requests/' . $r['id'] . '/approve') ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit" onclick="return confirm('اعتماد الطلب وإصدار الخطاب تلقائياً؟');">✅ إصدار</button>
                        </form>
                        <form method="post" action="<?= route('/forms/requests/' . $r['id'] . '/reject') ?>" style="display:inline;" onsubmit="var n=prompt('سبب الرفض (اختياري):');if(n===null)return false;this.querySelector('[name=note]').value=n;return true;">
                            <?= csrf_field() ?><input type="hidden" name="note" value="">
                            <button class="btn btn-outline btn-sm" type="submit">❌ رفض</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
