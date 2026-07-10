<?php
function install_header(string $title, int $activeStep): void
{
    $steps = [1 => 'المتطلبات', 2 => 'قاعدة البيانات', 3 => 'الحساب والشركة', 4 => 'اكتمل التثبيت'];
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> - معالج التثبيت</title>
<style>
:root{--primary:#2563eb;--bg:#f4f6f9;--card:#ffffff;--text:#1f2937;--muted:#6b7280;--border:#e5e7eb;--danger:#dc2626;--success:#16a34a;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:var(--bg);color:var(--text);direction:rtl;}
.wrap{max-width:640px;margin:40px auto;padding:0 16px;}
.logo{text-align:center;font-size:22px;font-weight:700;color:var(--primary);margin-bottom:18px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
.steps{display:flex;justify-content:space-between;margin-bottom:24px;list-style:none;padding:0;}
.steps li{flex:1;text-align:center;font-size:13px;color:var(--muted);position:relative;padding-top:26px;}
.steps li::before{content:"";position:absolute;top:0;right:calc(50% - 11px);width:22px;height:22px;border-radius:50%;background:#e5e7eb;}
.steps li.active{color:var(--primary);font-weight:700;}
.steps li.active::before{background:var(--primary);}
.steps li.done::before{background:var(--success);}
h1{font-size:19px;margin:0 0 6px;}
p.desc{color:var(--muted);margin:0 0 20px;font-size:14px;}
.field{margin-bottom:16px;}
label{display:block;margin-bottom:6px;font-size:14px;font-weight:600;}
input[type=text],input[type=email],input[type=password],input[type=number],select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;background:#fff;color:var(--text);}
input:focus,select:focus{outline:2px solid var(--primary);outline-offset:1px;}
.btn{display:inline-block;background:var(--primary);color:#fff;border:0;border-radius:8px;padding:11px 22px;font-size:15px;cursor:pointer;text-decoration:none;font-weight:600;}
.btn:hover{opacity:.92;}
.btn-block{width:100%;text-align:center;}
.req-list{list-style:none;padding:0;margin:0 0 20px;}
.req-list li{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px;}
.req-list li:last-child{border-bottom:0;}
.ok{color:var(--success);font-weight:700;}
.fail{color:var(--danger);font-weight:700;}
.alert{padding:12px 14px;border-radius:8px;margin-bottom:16px;font-size:14px;}
.alert-danger{background:#fef2f2;color:var(--danger);border:1px solid #fecaca;}
.alert-success{background:#f0fdf4;color:var(--success);border:1px solid #bbf7d0;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.hint{color:var(--muted);font-size:12px;margin-top:4px;}
.center{text-align:center;}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">معالج تثبيت النظام</div>
    <ul class="steps">
        <?php foreach ($steps as $num => $label): ?>
            <li class="<?= $num === $activeStep ? 'active' : ($num < $activeStep ? 'done' : '') ?>"><?= $label ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="card">
    <?php
}

function install_footer(): void
{
    ?>
    </div>
</div>
</body>
</html>
    <?php
}
