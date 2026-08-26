<?php
$isEdit = $task !== null;
$priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
$statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل مهمة' : 'إضافة مهمة' ?></h1></div>
</div>

<div class="card" style="max-width:700px;">
    <form method="post" action="<?= $isEdit ? route('/tasks/' . $task['id']) : route('/tasks') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عنوان المهمة</label>
            <input type="text" name="title" value="<?= e($task['title'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label>الوصف</label>
            <textarea name="description"><?= e($task['description'] ?? '') ?></textarea>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المسؤول عن المهمة</label>
                <select name="assignee_id" required>
                    <option value="">اختر مستخدماً</option>
                    <?php foreach ($companyUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int) ($task['assignee_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الأولوية</label>
                <select name="priority">
                    <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p ?>" <?= ($task['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= e($priorityLabels[$p]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>تاريخ البداية</label>
                <input type="date" name="start_date" value="<?= e($task['start_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label>تاريخ الاستحقاق</label>
                <input type="date" name="due_date" value="<?= e($task['due_date'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label>الحالة</label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= ($task['status'] ?? 'todo') === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($linkables)): ?>
        <?php
        $typeLabels = ['document' => 'مستند', 'asset' => 'أصل/عهدة', 'employee' => 'موظف', 'client' => 'عميل', 'crm_org' => 'جهة (CRM)'];
        $currentLink = ($task['linked_type'] ?? '') !== '' ? $task['linked_type'] . ':' . (int) ($task['linked_id'] ?? 0) : '';
        ?>
        <div class="field">
            <label>ربط بكيان (اختياري) — للتتبّع</label>
            <select name="linked">
                <option value="">— بدون —</option>
                <?php foreach ($linkables as $type => $rows): ?>
                    <?php if ($rows): ?>
                    <optgroup label="<?= e($typeLabels[$type] ?? $type) ?>">
                        <?php foreach ($rows as $r): ?>
                            <option value="<?= $type . ':' . $r['id'] ?>" <?= $currentLink === $type . ':' . $r['id'] ? 'selected' : '' ?>><?= e($r['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="field">
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="requires_approval" value="1" id="req_approval" style="width:auto;" <?= !empty($task['requires_approval']) ? 'checked' : '' ?>>
                تحتاج هذه المهمة إلى اعتماد
            </label>
        </div>
        <div class="field">
            <label>المعتمِد (عند الحاجة للاعتماد)</label>
            <select name="approver_id">
                <option value="">— بدون —</option>
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($task['approver_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء المهمة' ?></button>
            <a class="btn btn-outline" href="<?= route('/tasks') ?>">إلغاء</a>
        </div>
    </form>
</div>
