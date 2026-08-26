<?php
/** سجل العلاقة: ماذا جرى مع هذه الجهة بالترتيب الزمني + نموذج تسجيل نشاط. */
use Modules\Crm\Models\Activity;

$wid = (int) $workspace['id'];
$oid = (int) $organization['id'];
$types = Activity::types();
?>
<div class="card">
    <div class="card-title divided">
        <span>🕘 سجل العلاقة (<?= count($timeline) ?>)</span>
        <?php if ($canLogActivity): ?>
            <a class="btn btn-sm" href="#log-activity">+ تسجيل نشاط</a>
        <?php endif; ?>
    </div>

    <?php if (!$timeline): ?>
        <div class="empty-state" style="padding:20px;"><div class="ic">🕘</div>
            لم يبدأ التواصل مع هذه الجهة بعد — سجّل أول نشاط ليبدأ سجل العلاقة.
        </div>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;">
    <?php foreach ($timeline as $a): ?>
        <?php
        $icon = $types[$a['type']][0] ?? '•';
        $pending = $a['next_action_status'] === 'pending';
        $overdue = $pending && $a['next_action_at'] < date('Y-m-d H:i:s');
        ?>
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
            <div style="font-size:1.3em;line-height:1;"><?= $icon ?></div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <strong><?= e(Activity::typeLabel($a['type'])) ?></strong>
                    <?php if (!empty($a['subject'])): ?><span>— <?= e($a['subject']) ?></span><?php endif; ?>
                    <?php if (!empty($a['contact_name'])): ?>
                        <span class="badge badge-muted">👤 <?= e($a['contact_name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($a['body'])): ?>
                    <div style="margin-top:4px;white-space:pre-wrap;"><?= e($a['body']) ?></div>
                <?php endif; ?>
                <?php if (!empty($a['outcome'])): ?>
                    <div class="hint" style="margin-top:4px;">النتيجة: <?= e($a['outcome']) ?></div>
                <?php endif; ?>
                <div class="doc-log-meta" style="margin-top:4px;">
                    <?= e($a['user_name'] ?? 'النظام') ?> · <?= format_date($a['occurred_at'], 'Y-m-d H:i') ?>
                </div>

                <?php if ($pending): ?>
                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <span class="badge badge-<?= $overdue ? 'danger' : 'info' ?>">
                            🔔 متابعة <?= format_date($a['next_action_at'], 'Y-m-d') ?><?= $overdue ? ' (متأخرة)' : '' ?>
                        </span>
                        <?php if (!empty($a['next_action_note'])): ?><span class="hint"><?= e($a['next_action_note']) ?></span><?php endif; ?>
                        <?php if ($canLogActivity): ?>
                            <form method="post" action="<?= route('/crm/w/' . $wid . '/activities/' . $a['id'] . '/done') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline btn-sm" type="submit">✓ تمت</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($a['task_id'])): ?>
                            <a class="hint" href="<?= route('/tasks/' . $a['task_id']) ?>">↗ المهمة</a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($a['next_action_status'] === 'done'): ?>
                    <div class="hint" style="margin-top:6px;">✓ اكتملت متابعتها</div>
                <?php endif; ?>
            </div>
            <?php if ($canLogActivity && ((int) $a['user_id'] === (int) current_user()['id'] || ($membership['role'] ?? '') === 'manager')): ?>
                <form method="post" action="<?= route('/crm/w/' . $wid . '/activities/' . $a['id'] . '/delete') ?>" data-confirm="حذف هذا النشاط من السجل؟">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if ($canLogActivity): ?>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/' . $oid . '/activities') ?>" id="log-activity" style="margin-top:16px;">
        <?= csrf_field() ?>
        <div class="card-title divided"><span>➕ تسجيل نشاط</span></div>
        <div class="grid-2">
            <div class="field">
                <label>نوع النشاط</label>
                <select name="type">
                    <?php foreach ($types as $key => [$ic, $label]): ?>
                        <?php if ($key === 'stage_change') continue; ?>
                        <option value="<?= $key ?>"><?= $ic ?> <?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الشخص الذي تم التواصل معه</label>
                <select name="contact_id">
                    <option value="">— غير محدد —</option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?><?= $c['job_title'] ? ' — ' . e($c['job_title']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field"><label>العنوان (اختياري)</label><input type="text" name="subject" maxlength="200" placeholder="مثال: إرسال عرض الشراكة الإعلامية"></div>
        <div class="field"><label>ماذا جرى؟</label><textarea name="body" rows="3" placeholder="تفاصيل التواصل..."></textarea></div>
        <div class="grid-2">
            <div class="field"><label>النتيجة (اختياري)</label><input type="text" name="outcome" maxlength="255" placeholder="مثال: موافقة مبدئية"></div>
            <div class="field"><label>تاريخ ووقت النشاط</label><input type="datetime-local" name="occurred_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
        </div>

        <div class="card-title divided" style="margin-top:10px;"><span>🔔 المتابعة القادمة (اختياري)</span></div>
        <p class="hint" style="margin-top:0;">تحديد موعد يُنشئ مهمة حقيقية في «المهام» مرتبطة بهذه الجهة، فتظهر في مهامك وتقويمك وتصلك تنبيهاتها.</p>
        <div class="grid-2">
            <div class="field"><label>موعد المتابعة</label><input type="date" name="next_action_at"></div>
            <div class="field">
                <label>المسؤول عن المتابعة</label>
                <select name="next_action_owner">
                    <option value="">— أنا —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['user_id'] ?>"><?= e($m['user_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field"><label>ماذا نفعل في المتابعة؟</label><input type="text" name="next_action_note" maxlength="255" placeholder="مثال: الاتصال للتأكد من استلام العرض"></div>

        <div class="form-actions"><button class="btn" type="submit">حفظ النشاط</button></div>
    </form>
    <?php endif; ?>
</div>
