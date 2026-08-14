<?php use Modules\Archive\Models\ArchiveFile; ?>
<?php $isEdit = $file !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل بيانات ملف' : 'رفع ملف جديد' ?></h1>
        <?php if (!$isEdit): ?><p>ارفع الملف واختر تصنيفه — والباقي اختياري.</p><?php endif; ?>
    </div>
    <a class="btn btn-outline" href="<?= $isEdit ? route('/archive/' . $file['id']) : route('/archive') ?>">← رجوع</a>
</div>

<div class="card" style="max-width:720px;">
    <form method="post" action="<?= $isEdit ? route('/archive/' . $file['id']) : route('/archive/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <!-- الأساسيات: الملف + العنوان + التصنيف -->
        <?php if (!$isEdit): ?>
            <div class="field">
                <label>الملف <span class="req">*</span></label>
                <input type="file" name="file" required>
                <p class="hint">PDF, Word, Excel, PowerPoint, صور (PNG/JPG/WEBP/GIF), ZIP — بحد أقصى 25 ميجابايت.</p>
            </div>
        <?php else: ?>
            <p class="hint" style="margin-bottom:14px;">الملف الحالي: <strong><?= e($file['original_name']) ?></strong> (الإصدار <?= (int) $file['version'] ?>). لرفع نسخة جديدة استخدم «استبدال الملف» في صفحة الملف.</p>
        <?php endif; ?>

        <div class="grid-2">
            <div class="field">
                <label>عنوان الملف</label>
                <input type="text" name="title" value="<?= e($file['title'] ?? '') ?>" placeholder="اسم واضح للملف (اختياري)">
            </div>
            <div class="field">
                <label>التصنيف <span class="req">*</span></label>
                <select name="category_id" required>
                    <option value="">اختر تصنيفاً</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($file['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- المشاركة المباشرة (عند الرفع فقط) -->
        <?php if (!$isEdit && !empty($canShare)): ?>
        <div class="field" style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:var(--bg);">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin:0;">
                <input type="checkbox" name="create_share" id="ar-share" value="1" style="width:auto;">
                🔗 أنشئ رابط مشاركة مباشر بعد الرفع
            </label>
            <div id="ar-share-opts" style="display:none;margin-top:10px;">
                <div class="grid-2">
                    <div class="field" style="margin:0;">
                        <label>مدة صلاحية الرابط</label>
                        <select name="share_expires_in_days">
                            <option value="1">يوم واحد</option>
                            <option value="7" selected>٧ أيام</option>
                            <option value="30">٣٠ يوماً</option>
                            <option value="90">٩٠ يوماً</option>
                        </select>
                    </div>
                    <div class="field" style="margin:0;">
                        <label>حد التنزيلات (اختياري)</label>
                        <input type="number" name="share_max_downloads" min="1" max="10000" placeholder="بلا حد">
                    </div>
                </div>
                <p class="hint" style="margin-top:8px;">يظهر الرابط جاهزاً للنسخ بعد الرفع مباشرة. رابط عام لا يتطلب تسجيل دخول، ويُلغى تلقائياً عند انتهاء المدة.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- خيارات إضافية (مطوية) -->
        <details class="filters-collapse" style="margin-top:6px;"<?= $isEdit ? ' open' : '' ?>>
            <summary>⚙️ خيارات إضافية (وصف، وسوم، صلاحيات الرؤية، ربط...)</summary>
            <div style="margin-top:12px;">
                <div class="field">
                    <label>الوصف</label>
                    <textarea name="description" rows="2"><?= e($file['description'] ?? '') ?></textarea>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label>الكلمات المفتاحية</label>
                        <input type="text" name="keywords" value="<?= e($file['keywords'] ?? '') ?>" placeholder="افصل بينها بفاصلة">
                    </div>
                    <div class="field">
                        <label>الوسوم (Tags)</label>
                        <input type="text" name="tags" list="existing-tags" value="<?= e(implode(', ', array_column($fileTags, 'name'))) ?>" placeholder="مثال: عقود, 2026">
                        <datalist id="existing-tags">
                            <?php foreach ($allTags as $t): ?><option value="<?= e($t['name']) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label>من يستطيع رؤية هذا الملف؟</label>
                        <select name="visibility_type" id="file-visibility">
                            <?php foreach (ArchiveFile::visibilityLabels() as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($file['visibility_type'] ?? 'inherit') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>تاريخ انتهاء الصلاحية (اختياري)</label>
                        <input type="date" name="expires_at" value="<?= e($file['expires_at'] ?? '') ?>">
                    </div>
                </div>
                <div class="field" id="file-access-users-box" style="display:none;">
                    <label>المستخدمون المسموح لهم</label>
                    <select name="access_users[]" multiple size="6">
                        <?php foreach ($companyUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $accessUserIds, true) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>ملاحظات</label>
                    <textarea name="notes" rows="2"><?= e($file['notes'] ?? '') ?></textarea>
                </div>
                <?php if (!empty($linkables)): ?>
                <?php $curLinks = array_flip($currentLinks ?? []); ?>
                <div class="field">
                    <label>ربط بكيانات (اختياري) — لتتبّع الملف</label>
                    <select name="linked[]" multiple size="7" style="min-height:auto;">
                        <?php foreach ($linkables as $groupLabel => $rows): ?>
                            <?php if ($rows): ?>
                            <optgroup label="<?= e($groupLabel) ?>">
                                <?php foreach ($rows as $r): $val = $r['module'] . ':' . $r['id']; ?>
                                    <option value="<?= e($val) ?>" <?= isset($curLinks[$val]) ? 'selected' : '' ?>><?= e($r['label']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">اختيار متعدد: اضغط Ctrl (أو ⌘). «شخص» يشمل موظفي الملف الوظيفي وأصحاب العضويات.</p>
                </div>
                <?php endif; ?>
            </div>
        </details>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : '⬆️ رفع الملف' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/archive/' . $file['id']) : route('/archive') ?>">إلغاء</a>
        </div>
    </form>
</div>
<script>
(function () {
    var sel = document.getElementById('file-visibility');
    var box = document.getElementById('file-access-users-box');
    if (sel && box) {
        var sync = function () { box.style.display = sel.value === 'specific_users' ? '' : 'none'; };
        sel.addEventListener('change', sync); sync();
    }
    var sh = document.getElementById('ar-share'), opts = document.getElementById('ar-share-opts');
    if (sh && opts) {
        var t = function () { opts.style.display = sh.checked ? '' : 'none'; };
        sh.addEventListener('change', t); t();
    }
})();
</script>
