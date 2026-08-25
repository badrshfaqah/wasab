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

    <div class="form-section">🔕 ماذا يصلك كإشعار جوال؟</div>
    <?php
    $savedPrefs = !empty($u['push_prefs']) ? (json_decode((string) $u['push_prefs'], true) ?: []) : [];
    ?>
    <form method="post" action="<?= route('/profile/push-prefs') ?>">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;margin-bottom:10px;">
            <?php foreach (\App\Core\Notification::pushCategories() as $key => $label): ?>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin:0;">
                    <input type="checkbox" name="push_categories[]" value="<?= $key ?>" style="width:auto;"
                        <?= (!array_key_exists($key, $savedPrefs) || $savedPrefs[$key] !== false) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="hint" style="margin:0 0 10px;">الفئات غير المحددة تبقى تصلك داخل النظام لكنها لا تُرسل لجوالك.</p>
        <button class="btn btn-outline btn-sm" type="submit">حفظ التفضيلات</button>
    </form>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title"><span>✍️ توقيعاتي</span></div>
    <p class="hint" style="margin:0 0 12px;">ارفع توقيعك ليظهر كخيار عند توقيع المستندات والخطابات. يُفضّل صورة PNG بخلفية شفافة.</p>

    <?php if (!empty($signatures)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
            <?php foreach ($signatures as $sig): ?>
                <?php $sharedWith = $sig['shared_with'] ?? []; ?>
                <div style="border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center;width:190px;">
                    <img src="<?= e(\App\Core\UserSignature::imageUrl($sig)) ?>" alt="" style="max-height:56px;max-width:100%;">
                    <div class="hint" style="margin-top:6px;word-break:break-word;"><?= e($sig['name']) ?></div>
                    <form method="post" action="<?= route('/profile/signatures/' . $sig['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا التوقيع؟');" style="margin-top:6px;">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">حذف</button>
                    </form>
                    <?php if (!empty($companyUsers)): ?>
                    <details style="margin-top:8px;text-align:right;">
                        <summary class="hint" style="cursor:pointer;">🖋️ مشاركة التوقيع<?= $sharedWith ? ' (' . count($sharedWith) . ')' : '' ?></summary>
                        <form method="post" action="<?= route('/profile/signatures/' . $sig['id'] . '/share') ?>" style="margin-top:6px;">
                            <?= csrf_field() ?>
                            <div style="max-height:140px;overflow:auto;display:flex;flex-direction:column;gap:4px;">
                                <?php foreach ($companyUsers as $u): ?>
                                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:.85em;">
                                        <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" style="width:auto;"
                                            <?= in_array((int) $u['id'], $sharedWith, true) ? 'checked' : '' ?>>
                                        <?= e($u['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-outline btn-sm" type="submit" style="margin-top:6px;">حفظ المشاركة</button>
                            <p class="hint" style="margin:6px 0 0;">من تحدده يمكنه التوقيع بهذا التوقيع نيابةً عنك.</p>
                        </form>
                    </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= route('/profile/signatures') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field"><label>اسم التوقيع (للتمييز)</label><input type="text" name="name" maxlength="120" placeholder="مثال: توقيعي الرسمي"></div>
        <div class="field"><label>صورة التوقيع</label><input type="file" name="image" accept="image/png,image/jpeg,image/webp" required></div>
        <button class="btn" type="submit">➕ إضافة توقيع</button>
    </form>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title"><span>🪧 أختامي</span></div>
    <p class="hint" style="margin:0 0 12px;">ارفع أختامك لتظهر كخيار عند كتابة المستندات، ويمكنك مشاركة أي ختم مع زملاء تحددهم.</p>

    <?php if (!empty($stamps)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
            <?php foreach ($stamps as $st): ?>
                <?php $stSharedWith = $st['shared_with'] ?? []; ?>
                <div style="border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center;width:190px;">
                    <img src="<?= e(\App\Core\CompanyStamp::imageUrl($st)) ?>" alt="" style="max-height:70px;max-width:100%;">
                    <div class="hint" style="margin-top:6px;word-break:break-word;"><?= e($st['name']) ?></div>
                    <form method="post" action="<?= route('/profile/stamps/' . $st['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الختم؟');" style="margin-top:6px;">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">حذف</button>
                    </form>
                    <?php if (!empty($companyUsers)): ?>
                    <details style="margin-top:8px;text-align:right;">
                        <summary class="hint" style="cursor:pointer;">🪧 مشاركة الختم<?= $stSharedWith ? ' (' . count($stSharedWith) . ')' : '' ?></summary>
                        <form method="post" action="<?= route('/profile/stamps/' . $st['id'] . '/share') ?>" style="margin-top:6px;">
                            <?= csrf_field() ?>
                            <div style="max-height:140px;overflow:auto;display:flex;flex-direction:column;gap:4px;">
                                <?php foreach ($companyUsers as $u): ?>
                                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:.85em;">
                                        <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" style="width:auto;"
                                            <?= in_array((int) $u['id'], $stSharedWith, true) ? 'checked' : '' ?>>
                                        <?= e($u['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-outline btn-sm" type="submit" style="margin-top:6px;">حفظ المشاركة</button>
                            <p class="hint" style="margin:6px 0 0;">من تحدده يمكنه وضع هذا الختم على مستنداته.</p>
                        </form>
                    </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= route('/profile/stamps') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field"><label>اسم الختم (للتمييز)</label><input type="text" name="name" maxlength="120" placeholder="مثال: ختم الإدارة العامة"></div>
        <div class="field"><label>صورة الختم</label><input type="file" name="image" accept="image/png,image/jpeg,image/webp" required></div>
        <button class="btn" type="submit">➕ إضافة ختم</button>
    </form>
</div>
