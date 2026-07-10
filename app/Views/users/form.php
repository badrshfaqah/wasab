<?php
use App\Core\Auth;
$isEdit = $user !== null;
$isSysAdmin = Auth::isSystemAdmin();
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل مستخدم' : 'إضافة مستخدم' ?></h1></div>
</div>

<div class="card" style="max-width:620px;">
    <form method="post" action="<?= $isEdit ? route('/users/' . $user['id']) : route('/users') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>الاسم الكامل</label>
                <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>كلمة المرور <?= $isEdit ? '(اتركها فارغة لعدم التغيير)' : '' ?></label>
                <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>>
            </div>
            <div class="field">
                <label>الحالة</label>
                <select name="status">
                    <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>نشط</option>
                    <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>نوع العضوية</label>
                <select name="membership_type">
                    <?php if ($isSysAdmin): ?>
                        <option value="system_admin" <?= ($user['membership_type'] ?? '') === 'system_admin' ? 'selected' : '' ?>>مدير نظام</option>
                    <?php endif; ?>
                    <option value="company_admin" <?= ($user['membership_type'] ?? '') === 'company_admin' ? 'selected' : '' ?>>مدير شركة</option>
                    <option value="user" <?= ($user['membership_type'] ?? 'user') === 'user' ? 'selected' : '' ?>>مستخدم</option>
                </select>
            </div>
            <?php if ($isSysAdmin): ?>
            <div class="field">
                <label>الشركة</label>
                <select name="company_id">
                    <option value="">— بدون شركة (لمدير النظام) —</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($user['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($roles): ?>
        <div class="field">
            <label>الأدوار</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($roles as $r): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                        <input type="checkbox" name="roles[]" value="<?= $r['id'] ?>" style="width:auto;" <?= in_array((int) $r['id'], $userRoleIds, true) ? 'checked' : '' ?>>
                        <?= e($r['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint">الأدوار تحدد صلاحيات المستخدم العادي داخل الإضافات. مدير الشركة ومدير النظام يملكان صلاحيات كاملة تلقائياً.</p>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء المستخدم' ?></button>
            <a class="btn btn-outline" href="<?= route('/users') ?>">إلغاء</a>
        </div>
    </form>
</div>
