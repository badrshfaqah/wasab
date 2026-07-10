<?php
use App\Core\Auth;
$isEdit = $role !== null;
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل دور' : 'إضافة دور' ?></h1></div>
</div>

<div class="card" style="max-width:700px;">
    <form method="post" action="<?= $isEdit ? route('/roles/' . $role['id']) : route('/roles') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>اسم الدور</label>
                <input type="text" name="name" value="<?= e($role['name'] ?? '') ?>" required>
            </div>
            <?php if (Auth::isSystemAdmin()): ?>
            <div class="field">
                <label>الشركة</label>
                <select name="company_id" required>
                    <option value="">اختر الشركة</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($role['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label>الصلاحيات</label>
            <?php if (!$permissionGroups): ?>
                <div class="empty-state" style="padding:24px;"><div class="ic">🧩</div>لا توجد صلاحيات متاحة حالياً. فعّل إضافة أولاً من صفحة إدارة الإضافات.</div>
            <?php endif; ?>
            <?php foreach ($permissionGroups as $moduleKey => $group): ?>
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                    <div style="font-weight:700;margin-bottom:8px;">🧩 <?= e($group['label']) ?></div>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach ($group['permissions'] as $p): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                                <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" style="width:auto;" <?= in_array((int) $p['id'], $rolePermissionIds, true) ? 'checked' : '' ?>>
                                <?= e($p['label']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الدور' ?></button>
            <a class="btn btn-outline" href="<?= route('/roles') ?>">إلغاء</a>
        </div>
    </form>
</div>
