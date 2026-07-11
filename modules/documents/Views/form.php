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
                        <option value="<?= $t['id'] ?>" <?= (int) ($document['template_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label>تاريخ متابعة (اختياري)</label>
            <input type="date" name="follow_up_date" value="<?= e($document['follow_up_date'] ?? '') ?>">
            <p class="hint">يظهر هذا التاريخ كحدث بالتقويم الموحّد لتذكيرك بمتابعة هذا المستند.</p>
        </div>
        <div class="field">
            <label style="display:flex;align-items:center;gap:16px;font-weight:400;">
                <span style="display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="visibility" value="public" style="width:auto;" <?= ($document['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                    عام (يمر بمسار اعتماد وترقيم)
                </span>
                <span style="display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="visibility" value="private" style="width:auto;" <?= ($document['visibility'] ?? '') === 'private' ? 'checked' : '' ?>>
                    خاص (يُعتمد فوراً ويُرقَّم مباشرة)
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
