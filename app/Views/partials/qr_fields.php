<?php
/**
 * حقول رمز QR للتحقق لقالب مستند/نموذج + معاينة حية على ورقة A4 مصغّرة.
 * يتوقّع $template (مصفوفة القالب أو null)، و$qrBgUrl (رابط خلفية القالب أو null).
 * الإحداثيات بالبكسل على ورقة A4 الفعلية (794×1123 عند 96dpi) لتتطابق الطباعة.
 */
$qrEnabled = (int) ($template['qr_enabled'] ?? 0);
$qrX = (int) ($template['qr_x'] ?? 40);
$qrY = (int) ($template['qr_y'] ?? 40);
$qrSize = (int) ($template['qr_size'] ?? 90);
$qrColor = (string) ($template['qr_color'] ?? '#000000');
$sampleSvg = \App\Core\QrCode::svg('https://example.com/verify/sample-code-0123456789ab', 300);
$qrBgUrl = $qrBgUrl ?? null;
?>
<div class="field">
    <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="checkbox" name="qr_enabled" id="qr-enabled" value="1" <?= $qrEnabled ? 'checked' : '' ?> style="width:auto;">
        إظهار رمز QR للتحقق على المستند
    </label>
    <p class="hint">يشفّر رابط التحقق العام. حدّد موضعه وحجمه ولونه ليقع في فراغ الورقة الرسمية دون التأثير على شكلها.</p>
</div>

<div id="qr-settings" style="<?= $qrEnabled ? '' : 'display:none;' ?>display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:1;min-width:220px;">
        <div class="grid-2">
            <div class="field"><label>المسافة من اليسار (بكسل)</label><input type="number" name="qr_x" id="qr-x" value="<?= $qrX ?>" min="0" max="2000"></div>
            <div class="field"><label>المسافة من الأسفل (بكسل)</label><input type="number" name="qr_y" id="qr-y" value="<?= $qrY ?>" min="0" max="2000"></div>
            <div class="field"><label>الحجم (بكسل)</label><input type="number" name="qr_size" id="qr-size" value="<?= $qrSize ?>" min="40" max="400"></div>
            <div class="field"><label>اللون</label><input type="color" name="qr_color" id="qr-color" value="<?= e($qrColor) ?>" style="height:38px;padding:2px;"></div>
        </div>
    </div>
    <div>
        <label>معاينة</label>
        <div id="qr-preview-page" style="position:relative;width:240px;height:339px;border:1px solid var(--border);border-radius:6px;background:#fff center/cover no-repeat;<?= $qrBgUrl ? 'background-image:url(' . e($qrBgUrl) . ');' : '' ?>overflow:hidden;">
            <div id="qr-preview-box" style="position:absolute;color:<?= e($qrColor) ?>;line-height:0;"><?= $sampleSvg ?></div>
        </div>
        <p class="hint" style="max-width:240px;">الورقة مصغّرة لمقاس A4. الرمز الفعلي يُطبع بالبكسل المحدد.</p>
    </div>
</div>

<script>
(function () {
    var SCALE = 240 / 794; // عرض المعاينة ÷ عرض A4 بالبكسل (96dpi)
    var en = document.getElementById('qr-enabled'),
        settings = document.getElementById('qr-settings'),
        box = document.getElementById('qr-preview-box'),
        ix = document.getElementById('qr-x'), iy = document.getElementById('qr-y'),
        isz = document.getElementById('qr-size'), ic = document.getElementById('qr-color');
    function upd() {
        settings.style.display = en.checked ? 'flex' : 'none';
        var sz = Math.max(40, Math.min(400, +isz.value || 90));
        box.style.width = box.style.height = (sz * SCALE) + 'px';
        box.style.left = ((+ix.value || 0) * SCALE) + 'px';
        box.style.bottom = ((+iy.value || 0) * SCALE) + 'px';
        box.style.color = ic.value;
        var svg = box.querySelector('svg');
        if (svg) { svg.setAttribute('width', sz * SCALE); svg.setAttribute('height', sz * SCALE); }
    }
    [en, ix, iy, isz, ic].forEach(function (el) { if (el) { el.addEventListener('input', upd); el.addEventListener('change', upd); } });
    upd();
})();
</script>
