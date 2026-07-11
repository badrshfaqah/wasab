<?php
use Modules\Employees\Models\Employee;

$statusLabels = Employee::statusLabels();
$employmentTypeLabels = Employee::employmentTypeLabels();
$genderLabels = Employee::genderLabels();
$maritalStatusLabels = Employee::maritalStatusLabels();
$dependentRelationLabels = Employee::dependentRelationLabels();
$timelineEventLabels = Employee::timelineEventLabels();
$today = date('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1><?= e($employee['full_name']) ?></h1>
        <p><?= e($employee['job_title'] ?: '—') ?> · <?= e($employee['department'] ?: '—') ?> · <?= status_badge($employee['status']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canEdit): ?>
            <a class="btn btn-outline" href="<?= route('/employees/' . $employee['id'] . '/edit') ?>">تعديل</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/employees/' . $employee['id']) ?>" data-confirm="سيتم حذف الملف الوظيفي نهائياً مع كل مرفقاته. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="align-items:start;">
    <div>
        <div class="card">
            <div class="card-title"><span>نظرة عامة</span></div>
            <div style="display:flex;gap:14px;align-items:center;margin-bottom:10px;">
                <?php if ($employee['photo']): ?>
                    <img src="<?= e(base_url('storage/uploads/employees/' . $employee['photo'])) ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:64px;height:64px;border-radius:50%;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:26px;">🪪</div>
                <?php endif; ?>
                <div>
                    <div style="font-weight:700;"><?= e($employee['full_name']) ?></div>
                    <div class="hint"><?= e($employmentTypeLabels[$employee['employment_type']] ?? '') ?></div>
                </div>
            </div>
            <table>
                <tr><td class="hint">المدير المباشر</td><td><?= $manager ? '<a href="' . route('/employees/' . $manager['id']) . '">' . e($manager['full_name']) . '</a>' : '—' ?></td></tr>
                <tr><td class="hint">الفرع</td><td><?= e($employee['branch'] ?: '—') ?></td></tr>
                <tr><td class="hint">تاريخ الالتحاق</td><td><?= format_date($employee['hire_date']) ?></td></tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title"><span>البيانات الشخصية والتواصل</span></div>
            <table>
                <tr><td class="hint">رقم الهوية/الإقامة</td><td><?= e($employee['national_id'] ?: '—') ?></td></tr>
                <tr><td class="hint">الجنسية</td><td><?= e($employee['nationality'] ?: '—') ?></td></tr>
                <tr><td class="hint">تاريخ الميلاد</td><td><?= format_date($employee['birth_date']) ?></td></tr>
                <tr><td class="hint">الجنس</td><td><?= e($genderLabels[$employee['gender']] ?? '—') ?></td></tr>
                <tr><td class="hint">الحالة الاجتماعية</td><td><?= e($maritalStatusLabels[$employee['marital_status']] ?? '—') ?></td></tr>
                <tr><td class="hint">الجوال</td><td><?= e($employee['phone'] ?: '—') ?></td></tr>
                <tr><td class="hint">البريد الشخصي</td><td><?= e($employee['personal_email'] ?: '—') ?></td></tr>
                <tr><td class="hint">العنوان</td><td><?= e($employee['address'] ?: '—') ?></td></tr>
                <tr><td class="hint">جهة اتصال الطوارئ</td><td><?= e($employee['emergency_contact_name'] ?: '—') ?><?= $employee['emergency_contact_phone'] ? ' - ' . e($employee['emergency_contact_phone']) : '' ?><?= $employee['emergency_contact_relation'] ? ' (' . e($employee['emergency_contact_relation']) . ')' : '' ?></td></tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title"><span>المؤهلات</span></div>
            <table>
                <tr><td class="hint">المهارات</td><td><?= $employee['skills'] ? nl2br(e($employee['skills'])) : '—' ?></td></tr>
                <tr><td class="hint">اللغات</td><td><?= e($employee['languages'] ?: '—') ?></td></tr>
                <tr><td class="hint">رخصة القيادة</td><td><?= e($employee['driving_license_number'] ?: '—') ?><?= $employee['driving_license_expiry'] ? ' (تنتهي ' . format_date($employee['driving_license_expiry']) . ')' : '' ?></td></tr>
            </table>

            <div class="card-title" style="margin-top:16px;"><span>الدورات والشهادات</span></div>
            <?php foreach ($certifications as $c): ?>
                <div class="doc-log">
                    <div>
                        <?= e($c['title']) ?><?= $c['issuer'] ? ' - ' . e($c['issuer']) : '' ?>
                        <?php if ($c['expiry_date'] && $c['expiry_date'] < $today): ?><span class="badge badge-danger">منتهية</span><?php endif; ?>
                    </div>
                    <div class="doc-log-meta" style="display:flex;align-items:center;gap:8px;">
                        <span><?= $c['issue_date'] ? format_date($c['issue_date']) : '' ?><?= $c['expiry_date'] ? ' - حتى ' . format_date($c['expiry_date']) : '' ?></span>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= route('/employees/' . $employee['id'] . '/certifications/' . $c['id'] . '/delete') ?>" data-confirm="حذف هذا السجل؟">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$certifications): ?><p class="hint">لا توجد دورات أو شهادات مسجّلة.</p><?php endif; ?>
            <?php if ($canEdit): ?>
            <form method="post" action="<?= route('/employees/' . $employee['id'] . '/certifications') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field" style="margin:0;">
                        <input type="text" name="title" placeholder="عنوان الدورة/الشهادة" required>
                    </div>
                    <div class="field" style="margin:0;">
                        <input type="text" name="issuer" placeholder="الجهة المانحة (اختياري)">
                    </div>
                </div>
                <div class="grid-2" style="margin-top:8px;">
                    <div class="field" style="margin:0;">
                        <label class="hint">تاريخ الحصول</label>
                        <input type="date" name="issue_date">
                    </div>
                    <div class="field" style="margin:0;">
                        <label class="hint">تاريخ الانتهاء (اختياري)</label>
                        <input type="date" name="expiry_date">
                    </div>
                </div>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:8px;">إضافة</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($canViewSensitive): ?>
        <div class="card">
            <div class="card-title"><span>🔒 العقد والراتب</span></div>
            <table>
                <tr><td class="hint">نوع العقد</td><td><?= e($employee['contract_type'] ?: '—') ?></td></tr>
                <tr><td class="hint">بداية العقد</td><td><?= format_date($employee['contract_start_date']) ?></td></tr>
                <tr><td class="hint">نهاية العقد</td><td><?= format_date($employee['contract_end_date']) ?></td></tr>
                <tr><td class="hint">نهاية فترة التجربة</td><td><?= format_date($employee['probation_end_date']) ?></td></tr>
                <tr><td class="hint">الراتب الأساسي</td><td><?= $employee['salary_base'] !== null ? number_format((float) $employee['salary_base'], 2) : '—' ?></td></tr>
                <tr><td class="hint">البدلات</td><td><?= $employee['salary_allowances'] !== null ? number_format((float) $employee['salary_allowances'], 2) : '—' ?></td></tr>
                <tr><td class="hint">البنك</td><td><?= e($employee['bank_name'] ?: '—') ?></td></tr>
                <tr><td class="hint">الآيبان</td><td dir="ltr"><?= e($employee['bank_iban'] ?: '—') ?></td></tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title"><span>🔒 الأسرة والتأمين</span></div>
            <table>
                <tr><td class="hint">رقم التأمينات (GOSI)</td><td><?= e($employee['gosi_number'] ?: '—') ?></td></tr>
                <tr><td class="hint">التأمين الطبي</td><td><?= e($employee['medical_insurance_provider'] ?: '—') ?><?= $employee['medical_insurance_policy_number'] ? ' - ' . e($employee['medical_insurance_policy_number']) : '' ?></td></tr>
            </table>

            <div class="card-title" style="margin-top:16px;"><span>المُعالون</span></div>
            <?php foreach ($dependents as $d): ?>
                <div class="doc-log">
                    <div><?= e($d['name']) ?> <span class="hint">(<?= e($dependentRelationLabels[$d['relation']] ?? $d['relation']) ?>)</span></div>
                    <div class="doc-log-meta" style="display:flex;align-items:center;gap:8px;">
                        <span><?= $d['birth_date'] ? format_date($d['birth_date']) : '' ?></span>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= route('/employees/' . $employee['id'] . '/dependents/' . $d['id'] . '/delete') ?>" data-confirm="حذف هذا المُعال؟">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$dependents): ?><p class="hint">لا يوجد مُعالون مسجّلون.</p><?php endif; ?>
            <?php if ($canEdit): ?>
            <form method="post" action="<?= route('/employees/' . $employee['id'] . '/dependents') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field" style="margin:0;">
                        <input type="text" name="name" placeholder="الاسم" required>
                    </div>
                    <div class="field" style="margin:0;">
                        <select name="relation">
                            <?php foreach ($dependentRelationLabels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field" style="margin:8px 0 0;">
                    <label class="hint">تاريخ الميلاد (اختياري)</label>
                    <input type="date" name="birth_date">
                </div>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:8px;">إضافة</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($canManage && $employee['admin_notes']): ?>
        <div class="card">
            <div class="card-title"><span>ملاحظات إدارية</span></div>
            <p><?= nl2br(e($employee['admin_notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-title"><span>المستندات (<?= count($documents) ?>)</span></div>
            <?php foreach ($documents as $doc): ?>
                <div class="doc-log">
                    <div><a href="<?= route('/employees/' . $employee['id'] . '/documents/' . $doc['id']) ?>"><?= e($doc['title']) ?></a></div>
                    <div class="doc-log-meta" style="display:flex;align-items:center;gap:8px;">
                        <span><?= format_date($doc['created_at'], 'Y-m-d') ?></span>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= route('/employees/' . $employee['id'] . '/documents/' . $doc['id'] . '/delete') ?>" data-confirm="حذف هذا المستند؟">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$documents): ?><p class="hint">لا توجد مستندات مرفوعة.</p><?php endif; ?>
            <?php if ($canEdit): ?>
            <form method="post" action="<?= route('/employees/' . $employee['id'] . '/documents') ?>" enctype="multipart/form-data" style="margin-top:10px;">
                <?= csrf_field() ?>
                <div class="field" style="margin:0;">
                    <input type="text" name="title" placeholder="عنوان المستند (اختياري)">
                </div>
                <div class="field" style="margin:8px 0 0;">
                    <input type="file" name="file" required>
                </div>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:8px;">رفع</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title"><span>السجل الوظيفي</span></div>
            <?php foreach ($timeline as $t): ?>
                <div class="doc-log">
                    <div>
                        <span class="badge badge-info"><?= e($timelineEventLabels[$t['event_type']] ?? $t['event_type']) ?></span>
                        <?= nl2br(e($t['description'])) ?>
                    </div>
                    <div class="doc-log-meta" style="display:flex;align-items:center;gap:8px;">
                        <span><?= format_date($t['event_date']) ?> · <?= e($t['creator_name'] ?? 'النظام') ?></span>
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= route('/employees/' . $employee['id'] . '/timeline/' . $t['id'] . '/delete') ?>" data-confirm="حذف هذا السجل؟">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$timeline): ?><p class="hint">لا توجد سجلات بعد.</p><?php endif; ?>
            <?php if ($canEdit): ?>
            <form method="post" action="<?= route('/employees/' . $employee['id'] . '/timeline') ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field" style="margin:0;">
                        <select name="event_type">
                            <?php foreach ($timelineEventLabels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $key === 'note' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="margin:0;">
                        <input type="date" name="event_date" value="<?= e($today) ?>">
                    </div>
                </div>
                <textarea name="description" placeholder="تفاصيل الحدث..." style="margin-top:8px;"></textarea>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:8px;">إضافة للسجل</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
