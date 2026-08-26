<?php $wid = (int) $workspace['id']; ?>
<div class="page-head">
    <div>
        <h1>إضافة جهة إلى: <?= e($workspace['name']) ?></h1>
        <p>ابحث أولاً في دليل الجهات — إن كانت الجهة مسجّلة سابقاً في أي مساحة نربطها هنا بدل تكرار بياناتها.</p>
    </div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ رجوع</a>
</div>

<div class="card" style="max-width:820px;">
    <div class="card-title"><span>🔎 الخطوة 1: ابحث في الدليل المركزي</span></div>
    <form method="get" action="<?= route('/crm/w/' . $wid . '/orgs/create') ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="اكتب اسم الجهة..." style="flex:1;min-width:220px;">
        <button class="btn btn-sm" type="submit">بحث</button>
    </form>

    <?php if ($q !== ''): ?>
        <?php if (!$matches): ?>
            <p class="hint" style="margin-top:12px;">لا توجد جهة بهذا الاسم في الدليل — أنشئها بالأسفل.</p>
        <?php else: ?>
            <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($matches as $m): ?>
                    <div class="doc-log" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                        <div>
                            <strong><?= e($m['name']) ?></strong>
                            <div class="hint"><?= e(trim(($m['sector'] ?? '') . ' · ' . ($m['city'] ?? ''), ' ·')) ?: 'بلا تفاصيل' ?></div>
                        </div>
                        <?php if (!empty($m['already_linked'])): ?>
                            <span class="badge badge-muted">مرتبطة بهذه المساحة</span>
                        <?php else: ?>
                            <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs/link') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="organization_id" value="<?= $m['id'] ?>">
                                <button class="btn btn-sm" type="submit">ربط بالمساحة</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card" style="max-width:820px;">
    <div class="card-title"><span>🏢 الخطوة 2: جهة جديدة</span></div>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/orgs') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>اسم الجهة</label>
                <input type="text" name="name" value="<?= e($q) ?>" required>
            </div>
            <div class="field">
                <label>الاسم التجاري (اختياري)</label>
                <input type="text" name="trade_name">
            </div>
        </div>
        <div class="grid-2">
            <div class="field"><label>القطاع</label><input type="text" name="sector" placeholder="فعاليات، صحة، تقنية..."></div>
            <div class="field"><label>الشعار (اختياري)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الدولة</label><input type="text" name="country"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city"></div>
        </div>
        <div class="field"><label>العنوان</label><input type="text" name="address"></div>
        <div class="grid-2">
            <div class="field"><label>البريد الإلكتروني العام</label><input type="email" name="email"></div>
            <div class="field"><label>الهاتف</label><input type="text" name="phone"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الموقع الإلكتروني</label><input type="text" name="website" placeholder="https://"></div>
            <div class="field"><label>LinkedIn</label><input type="text" name="social_linkedin"></div>
        </div>
        <?php if ($categories): ?>
        <div class="field">
            <label>تصنيف الجهة داخل هذه المساحة</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($categories as $c): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                        <input type="checkbox" name="categories[]" value="<?= $c['id'] ?>" style="width:auto;">
                        <?= e($c['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint">يمكن للجهة أن تحمل أكثر من تصنيف، والتصنيف يخص هذه المساحة وحدها.</p>
        </div>
        <?php endif; ?>
        <div class="field"><label>وصف مختصر</label><textarea name="description" rows="2"></textarea></div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>

        <div class="form-actions">
            <button class="btn" type="submit">حفظ وإضافة للمساحة</button>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">إلغاء</a>
        </div>
    </form>
</div>
