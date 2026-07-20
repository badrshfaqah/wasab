<?php $u = current_user(); ?>
<div class="page-head"><div><h1>الملف الشخصي</h1></div></div>

<div class="card" style="max-width:480px;">
    <form method="post" action="<?= route('/profile') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>الاسم</label>
            <input type="text" name="name" value="<?= e($u['name']) ?>" required>
        </div>
        <div class="field">
            <label>البريد الإلكتروني</label>
            <input type="email" value="<?= e($u['email']) ?>" disabled>
        </div>
        <div class="field">
            <label>كلمة مرور جديدة (اتركها فارغة لعدم التغيير)</label>
            <input type="password" name="password">
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ</button></div>
    </form>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title"><span>📱 تنبيهات الجوال</span></div>
    <p class="hint" id="push-status" style="margin:0 0 12px;">جارٍ التحقق من حالة التنبيهات على هذا الجهاز...</p>
    <button class="btn" type="button" id="push-toggle">تفعيل التنبيهات على هذا الجهاز</button>
    <p class="hint" style="margin-top:12px;">
        لأفضل تجربة على الجوال: افتح النظام من متصفح الجوال ثم اختر "إضافة إلى الشاشة الرئيسية"
        ليتحول لتطبيق مستقل. على آيفون التفعيل يعمل فقط بعد الإضافة للشاشة الرئيسية (iOS 16.4+).
    </p>
</div>
