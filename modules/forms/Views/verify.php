<?php
/** صفحة تحقّق عامة مستقلة (بلا تخطيط النظام). لا تعرض نص الخطاب إطلاقاً. */
$found = $letter !== null;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>التحقق من خطاب</title>
<style>
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;color:#1f2937;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:20px;}
.card{background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:520px;width:100%;overflow:hidden;}
.head{padding:28px 24px;text-align:center;color:#fff;}
.head.ok{background:linear-gradient(135deg,#059669,#10b981);}
.head.bad{background:linear-gradient(135deg,#dc2626,#ef4444);}
.head .ic{font-size:44px;}
.head h1{margin:8px 0 0;font-size:20px;}
.body{padding:22px 24px;}
.row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:14px;}
.row:last-child{border-bottom:0;}
.row .k{color:#6b7280;}
.row .v{font-weight:600;text-align:left;}
.note{margin-top:16px;font-size:12px;color:#9ca3af;text-align:center;line-height:1.7;}
</style>
</head>
<body>
<div class="card">
    <?php if (!$found): ?>
        <div class="head bad">
            <div class="ic">⚠️</div>
            <h1>لم يُعثر على خطاب بهذا الرمز</h1>
        </div>
        <div class="body">
            <p style="text-align:center;color:#6b7280;">رمز التحقق غير صحيح أو أن الخطاب لم يعد متاحاً. يرجى التأكد من الرمز/الرابط.</p>
        </div>
    <?php else: ?>
        <div class="head ok">
            <div class="ic">✅</div>
            <h1>خطاب موثّق</h1>
        </div>
        <div class="body">
            <div class="row"><span class="k">الجهة</span><span class="v"><?= e($letter['company_name'] ?? '—') ?></span></div>
            <?php if (!empty($letter['number'])): ?>
                <div class="row"><span class="k">رقم الخطاب</span><span class="v"><?= e($letter['number']) ?></span></div>
            <?php endif; ?>
            <div class="row"><span class="k">العنوان</span><span class="v"><?= e($letter['title']) ?></span></div>
            <?php if (!empty($letter['recipient_name'])): ?>
                <div class="row"><span class="k">المستفيد</span><span class="v"><?= e($letter['recipient_name']) ?></span></div>
            <?php endif; ?>
            <div class="row"><span class="k">تاريخ الإصدار</span><span class="v"><?= e(date('Y-m-d', strtotime($letter['created_at']))) ?></span></div>
            <p class="note">هذه الصفحة تؤكد وجود الخطاب وبياناته الأساسية في النظام. نص الخطاب لا يُعرض هنا حفاظاً على الخصوصية.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
