<?php
use App\Core\View;
use Modules\Documents\Models\Document;

$isEdit = $document !== null;
$typeLabels = Document::typeLabels();
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل مستند' : 'مستند جديد' ?></h1></div>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= $isEdit ? route('/documents/' . $document['id']) : route('/documents') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عنوان المستند</label>
            <input type="text" name="title" value="<?= e($document['title'] ?? '') ?>" required>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>نوع المستند</label>
                <select name="type">
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= ($document['type'] ?? 'general') === $t ? 'selected' : '' ?>><?= e($typeLabels[$t]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>القالب (اختياري)</label>
                <select name="template_id">
                    <option value="">— بدون قالب —</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (int) ($document['template_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?><?= !empty($t['owner_name']) ? ' (مشاركة من ' . e($t['owner_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">قوالبك + المشارَكة معك. أنشئ قوالبك من <a href="<?= route('/documents/templates') ?>" target="_blank">صفحة القوالب</a>.</p>
            </div>
        </div>

        <!-- كتلة التوقيع: توقيع/ختم يُختاران أثناء الكتابة + سطرا المسمى والاسم -->
        <div class="grid-2">
            <div class="field">
                <label>التوقيع على الورقة (اختياري)</label>
                <select name="signature_id">
                    <option value="">— بلا توقيع —</option>
                    <?php foreach (($mySignatures ?? []) as $sig): ?>
                        <option value="<?= $sig['id'] ?>" <?= (int) ($document['signature_id'] ?? 0) === (int) $sig['id'] ? 'selected' : '' ?>><?= e($sig['name']) ?><?= !empty($sig['owner_name']) ? ' (مشاركة من ' . e($sig['owner_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">تواقيعك + المشارَكة معك — تُضاف من <a href="<?= route('/profile') ?>" target="_blank">ملفك الشخصي</a>.</p>
            </div>
            <div class="field">
                <label>الختم على الورقة (اختياري)</label>
                <select name="stamp_id">
                    <option value="">— بلا ختم<?= empty($document['template_id']) ? '' : ' (يُستخدم ختم القالب إن وُجد)' ?> —</option>
                    <?php foreach (($myStamps ?? []) as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= (int) ($document['stamp_id'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?><?= !empty($st['owner_name']) ? ' (مشاركة من ' . e($st['owner_name']) . ')' : (empty($st['user_id']) ? ' (مكتبة الشركة)' : '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المسمى فوق التوقيع (اختياري)</label>
                <input type="text" name="signer_title" maxlength="150" value="<?= e($document['signer_title'] ?? '') ?>" placeholder="مثال: مدير عام الشركة">
            </div>
            <div class="field">
                <label>اسم الموقّع (اختياري)</label>
                <input type="text" name="signer_name" maxlength="150" value="<?= e($document['signer_name'] ?? '') ?>" placeholder="مثال: فلان الفلاني — أو اتركه فارغاً">
                <p class="hint">يمكن وضع التوقيع والختم دون كتابة اسم.</p>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>تاريخ متابعة (اختياري)</label>
                <input type="date" name="follow_up_date" value="<?= e($document['follow_up_date'] ?? '') ?>">
                <p class="hint">يظهر كحدث بالتقويم الموحّد لتذكيرك بمتابعة المستند.</p>
            </div>
            <div class="field">
                <label>تاريخ انتهاء الصلاحية (اختياري)</label>
                <input type="date" name="expiry_date" value="<?= e($document['expiry_date'] ?? '') ?>">
                <p class="hint">يُوسَم المستند بعد هذا التاريخ كمنتهي الصلاحية.</p>
            </div>
        </div>
        <div class="field">
            <label>تصنيف السرية</label>
            <select name="confidentiality">
                <?php foreach (\Modules\Documents\Models\Document::confidentialityLabels() as $ck => $cl): ?>
                    <option value="<?= $ck ?>" <?= ($document['confidentiality'] ?? 'normal') === $ck ? 'selected' : '' ?>><?= e($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label style="display:flex;align-items:center;gap:16px;font-weight:400;">
                <span style="display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="visibility" value="public" style="width:auto;" <?= ($document['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                    عام (يظهر لكل الموظفين في «مستندات الشركة»)
                </span>
                <span style="display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="visibility" value="private" style="width:auto;" <?= ($document['visibility'] ?? '') === 'private' ? 'checked' : '' ?>>
                    خاص (لا يراه إلا أنت ومن تشاركه معه)
                </span>
            </label>
        </div>
        <div class="field">
            <label>محتوى المستند</label>
            <?= View::renderPartial('documents::partials.editor', [
                'name' => 'content',
                'id' => 'doc-content',
                'value' => $document['content'] ?? '',
            ]) ?>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء المستند' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/documents/' . $document['id']) : route('/documents') ?>">إلغاء</a>
        </div>
    </form>
</div>
