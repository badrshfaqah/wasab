<?php $isEdit = $workspace !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'إعدادات المساحة' : 'مساحة CRM جديدة' ?></h1>
        <?php if (!$isEdit): ?><p>حدد اسم المساحة ومسؤولها وأعضاءها — وتُنشأ بتصنيفات ومسار عمل ابتدائي جاهز للتعديل.</p><?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($isEdit): ?><a class="btn btn-outline" href="<?= route('/crm/w/' . $workspace['id'] . '/members') ?>">👥 الأعضاء</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= $isEdit ? route('/crm/w/' . $workspace['id']) : route('/crm') ?>">↩ رجوع</a>
    </div>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= $isEdit ? route('/crm/w/' . $workspace['id'] . '/edit') : route('/crm/workspaces') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم المساحة</label>
            <input type="text" name="name" value="<?= e($workspace['name'] ?? '') ?>" required placeholder="مثال: تفاصيل الفعاليات">
        </div>
        <div class="field">
            <label>وصف مختصر (اختياري)</label>
            <input type="text" name="description" maxlength="500" value="<?= e($workspace['description'] ?? '') ?>" placeholder="ما الغرض من هذه المساحة؟">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>الأيقونة</label>
                <input type="text" name="icon" maxlength="4" value="<?= e($workspace['icon'] ?? '🤝') ?>" style="width:90px;text-align:center;font-size:1.2em;">
                <p class="hint">رمز تعبيري يميّز المساحة في القوائم.</p>
            </div>
            <div class="field">
                <label>اللون</label>
                <input type="color" name="color" value="<?= e($workspace['color'] ?? '#2563eb') ?>" style="width:80px;height:38px;padding:2px;">
            </div>
        </div>
        <div class="field">
            <label>المسؤول عن المساحة</label>
            <select name="manager_id">
                <option value="">— أنا —</option>
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($workspace['manager_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">يحصل تلقائياً على دور «مدير المساحة» بكل الصلاحيات داخلها.</p>
        </div>

        <?php if (!$isEdit): ?>
        <div class="field">
            <label>أعضاء المساحة</label>
            <div style="max-height:200px;overflow:auto;display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:8px;padding:10px;">
                <?php foreach ($companyUsers as $u): ?>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                        <input type="checkbox" name="member_ids[]" value="<?= $u['id'] ?>" style="width:auto;">
                        <?= e($u['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint">يُضافون بدور «عضو» ويمكن ضبط صلاحيات كل واحد لاحقاً من شاشة الأعضاء. من ليس عضواً لا يرى المساحة إطلاقاً.</p>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ الإعدادات' : 'إنشاء المساحة' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/crm/w/' . $workspace['id']) : route('/crm') ?>">إلغاء</a>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<div class="card" style="max-width:820px;">
    <div class="card-title"><span>🗄️ حالة المساحة</span></div>
    <p class="hint" style="margin-top:0;">
        <?= $workspace['status'] === 'active'
            ? 'الأرشفة تُخفي المساحة من القوائم دون حذف أي بيانات، ويمكن إعادتها في أي وقت.'
            : 'هذه المساحة مؤرشفة حالياً.' ?>
    </p>
    <form method="post" action="<?= route('/crm/w/' . $workspace['id'] . '/archive') ?>" data-confirm="<?= $workspace['status'] === 'active' ? 'أرشفة هذه المساحة؟' : 'إعادة تنشيط المساحة؟' ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline" type="submit"><?= $workspace['status'] === 'active' ? '🗄️ أرشفة المساحة' : '↩️ إعادة التنشيط' ?></button>
    </form>
</div>
<?php endif; ?>
