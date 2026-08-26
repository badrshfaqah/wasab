<div class="page-head">
    <div>
        <h1>استيراد العملاء إلى الدليل</h1>
        <p>العميل في وصاب ليس إلا جهة صفتها عميل — ننقل سجلات إضافة «العملاء» إلى الدليل الموحّد: الشركات تصبح جهات، والأفراد أفراداً، وجهة التواصل شخصاً مرتبطاً بجهته.</p>
    </div>
    <a class="btn btn-outline" href="<?= route('/contacts') ?>">↩ الدليل</a>
</div>

<?php if (!$available): ?>
    <div class="card"><div class="empty-state"><div class="ic">📇</div>
        لا توجد سجلات في إضافة «العملاء» القديمة — لا شيء لاستيراده.
    </div></div>
<?php elseif (!$clients): ?>
    <div class="card"><div class="empty-state"><div class="ic">✅</div>
        لا يوجد عملاء مسجّلون في الإضافة القديمة — لا حاجة للاستيراد.
    </div></div>
<?php else: ?>
<div class="card">
    <div class="card-title"><span>ما سيُستورد (<?= count($clients) ?> سجلاً)</span></div>
    <?php if ($alreadyIn): ?>
        <p class="hint"><?= (int) $alreadyIn ?> منها موجود في الدليل بالفعل وسيُتجاهل — الاستيراد آمن للتكرار.</p>
    <?php endif; ?>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>العميل</th><th>سيصبح</th><th>جهة التواصل</th><th>الهاتف</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><span class="badge badge-muted"><?= $c['is_company'] ? '🏢 جهة' : '👤 فرد' ?></span></td>
                <td><?= e($c['contact_name'] ?: '—') ?></td>
                <td><?= e($c['phone'] ?: '—') ?></td>
                <td><?= $c['already'] ? '<span class="badge badge-warning">موجود — سيُتجاهل</span>' : '<span class="badge badge-success">سيُضاف</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <form method="post" action="<?= route('/contacts/import-clients') ?>" style="margin-top:14px;">
        <?= csrf_field() ?>
        <p class="hint">لن يُحذف شيء من إضافة «العملاء» — تبقى كما هي حتى تقرر تعطيلها بعد التأكد.</p>
        <button class="btn" type="submit">تنفيذ الاستيراد</button>
    </form>
</div>
<?php endif; ?>
