<?php
$u = current_user();

/** مناطق زمنية شائعة بأسماء عربية - القيمة الفارغة تعني توقيت النظام الافتراضي (الرياض). */
$timezones = [
    'Asia/Riyadh' => 'الرياض (+03)',
    'Asia/Kuwait' => 'الكويت (+03)',
    'Asia/Qatar' => 'الدوحة (+03)',
    'Asia/Bahrain' => 'المنامة (+03)',
    'Asia/Baghdad' => 'بغداد (+03)',
    'Asia/Amman' => 'عمّان (+03)',
    'Asia/Aden' => 'صنعاء (+03)',
    'Europe/Istanbul' => 'إسطنبول (+03)',
    'Asia/Dubai' => 'دبي (+04)',
    'Asia/Muscat' => 'مسقط (+04)',
    'Africa/Cairo' => 'القاهرة (+02/+03)',
    'Asia/Beirut' => 'بيروت (+02/+03)',
    'Asia/Damascus' => 'دمشق (+03)',
    'Africa/Khartoum' => 'الخرطوم (+02)',
    'Africa/Tripoli' => 'طرابلس (+02)',
    'Africa/Tunis' => 'تونس (+01)',
    'Africa/Algiers' => 'الجزائر (+01)',
    'Africa/Casablanca' => 'الدار البيضاء (+01)',
    'Europe/London' => 'لندن',
    'Europe/Paris' => 'باريس',
    'America/New_York' => 'نيويورك',
];
?>
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
        <div class="field">
            <label>المنطقة الزمنية لعرض التواريخ والأوقات</label>
            <select name="timezone">
                <option value="">التوقيت الافتراضي للنظام (الرياض +03)</option>
                <?php foreach ($timezones as $tzKey => $tzLabel): ?>
                    <option value="<?= e($tzKey) ?>" <?= ($u['timezone'] ?? '') === $tzKey ? 'selected' : '' ?>><?= e($tzLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">تؤثر على عرض أوقات الرسائل والاجتماعات والإشعارات لك فقط - بقية المستخدمين يرون توقيتهم.</p>
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ</button></div>
    </form>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title"><span>📱 تنبيهات الجوال</span></div>
    <p class="hint" id="push-status" style="margin:0 0 12px;">جارٍ التحقق من حالة التنبيهات على هذا الجهاز...</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn" type="button" id="push-toggle">تفعيل التنبيهات على هذا الجهاز</button>
        <form method="post" action="<?= route('/push/test') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline" type="submit">📤 إرسال تنبيه تجريبي</button>
        </form>
    </div>
    <p class="hint" style="margin-top:12px;">
        لأفضل تجربة على الجوال: ثبّت النظام كتطبيق مستقل على شاشتك.
        <a href="<?= route('/get-app') ?>">اعرض خطوات التثبيت المصوّرة (آيفون وأندرويد) ←</a>
    </p>
</div>
