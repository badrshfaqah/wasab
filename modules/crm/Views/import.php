<?php $wid = (int) $workspace['id']; ?>
<div class="page-head">
    <div>
        <h1>استيراد جهات — <?= e($workspace['name']) ?></h1>
        <p>ارفع ملف CSV، طابِق أعمدته بحقول النظام، وراجع المعاينة قبل التنفيذ.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canExport): ?>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/export') ?>">⬇️ تصدير الجهات</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">↩ المساحة</a>
    </div>
</div>

<?php if ($step === 'upload'): ?>
<div class="card" style="max-width:760px;">
    <div class="card-title"><span>📥 الخطوة 1: رفع الملف</span></div>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/import/preview') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>ملف CSV</label>
            <input type="file" name="csv" accept=".csv,text/csv" required>
            <p class="hint">الصف الأول عناوين الأعمدة. يدعم الفاصلة (,) والفاصلة المنقوطة (;) وترميز UTF-8. الحد الأقصى 3 ميجابايت و2000 صف.</p>
        </div>
        <button class="btn" type="submit">قراءة الملف ومعاينته</button>
    </form>

    <div class="card-title divided" style="margin-top:18px;"><span>الحقول المدعومة</span></div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php foreach ($fields as $key => $label): ?>
            <span class="badge badge-muted"><?= e($label) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-title"><span>🔗 الخطوة 2: مطابقة الأعمدة (<?= (int) $total ?> صف)</span></div>
    <?php if ($duplicates): ?>
        <p class="hint" style="color:var(--warning);">⚠️ يبدو أن <?= (int) $duplicates ?> من أول 50 صفاً تطابق جهات موجودة في الدليل — اختر ماذا نفعل بها بالأسفل.</p>
    <?php endif; ?>

    <form method="post" action="<?= route('/crm/w/' . $wid . '/import/run') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="table-wrap">
        <table class="table-cards">
            <thead>
                <tr>
                    <?php foreach ($header as $i => $col): ?>
                        <th style="min-width:150px;">
                            <div class="hint" style="margin-bottom:4px;"><?= e($col) ?></div>
                            <select name="map[<?= $i ?>]">
                                <option value="">— تجاهل —</option>
                                <?php foreach ($fields as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($guesses[$i] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($header as $i => $col): ?>
                        <td><?= e(mb_substr((string) ($row[$i] ?? ''), 0, 40)) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="hint">تُعرض أول 10 صفوف للمعاينة فقط، وسيُنفَّذ الاستيراد على الملف كاملاً.</p>

        <div class="field" style="max-width:520px;">
            <label>إذا كانت الجهة موجودة في الدليل</label>
            <select name="on_duplicate">
                <option value="skip">اربطها بالمساحة دون تعديل بياناتها (موصى به)</option>
                <option value="update">حدّث بياناتها من الملف</option>
            </select>
            <p class="hint">في الحالتين لا تتكرر الجهة في الدليل المركزي — تُربط بالمساحة فقط.</p>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">تنفيذ الاستيراد</button>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/import') ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>
