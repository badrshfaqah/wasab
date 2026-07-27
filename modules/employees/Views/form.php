<?php
use Modules\Employees\Models\Employee;

$isEdit = $employee !== null;
$statusLabels = Employee::statusLabels();
$employmentTypeLabels = Employee::employmentTypeLabels();
$genderLabels = Employee::genderLabels();
$maritalStatusLabels = Employee::maritalStatusLabels();
$e = $employee ?? [];
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل: ' . e($e['full_name']) : 'ملف وظيفي جديد' ?></h1></div>
</div>

<div class="card" style="max-width:900px;">
    <form method="post" action="<?= $isEdit ? route('/employees/' . $e['id']) : route('/employees') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="card-title"><span>نظرة عامة</span></div>
        <div class="grid-2">
            <div class="field">
                <label>الاسم الكامل</label>
                <input type="text" name="full_name" value="<?= e($e['full_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>الصورة الشخصية (اختياري)</label>
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
                <?php if (!empty($e['photo'])): ?>
                    <div style="margin-top:8px;"><img src="<?= e(route('/media/employees/' . $e['company_id'] . '/' . $e['photo'])) ?>" alt="" style="height:48px;border-radius:8px;"></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المسمى الوظيفي</label>
                <input type="text" name="job_title" value="<?= e($e['job_title'] ?? '') ?>">
            </div>
            <div class="field">
                <label>القسم</label>
                <input type="text" name="department" value="<?= e($e['department'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>المدير المباشر</label>
                <select name="manager_employee_id">
                    <option value="">— بدون —</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (int) ($e['manager_employee_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الفرع</label>
                <input type="text" name="branch" value="<?= e($e['branch'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>تاريخ الالتحاق</label>
                <input type="date" name="hire_date" value="<?= e($e['hire_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label>نوع التوظيف</label>
                <select name="employment_type">
                    <?php foreach ($employmentTypeLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($e['employment_type'] ?? 'full_time') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label>حالة الموظف</label>
            <select name="status">
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($e['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="card-title" style="margin-top:20px;"><span>حساب الدخول</span></div>
        <div class="field">
            <label>ربط بحساب دخول موجود (اختياري)</label>
            <select name="linked_user_id">
                <option value="">— بدون حساب دخول —</option>
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($e['linked_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <p class="hint">لو استقال الموظف وحُذف حسابه لاحقاً، يبقى هذا الملف الوظيفي كاملاً بلا أي تأثير.</p>
        </div>

        <div class="card-title" style="margin-top:20px;"><span>البيانات الشخصية</span></div>
        <div class="grid-2">
            <div class="field">
                <label>رقم الهوية/الإقامة</label>
                <input type="text" name="national_id" value="<?= e($e['national_id'] ?? '') ?>">
            </div>
            <div class="field">
                <label>الجنسية</label>
                <input type="text" name="nationality" value="<?= e($e['nationality'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>تاريخ الميلاد</label>
                <input type="date" name="birth_date" value="<?= e($e['birth_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label>الجنس</label>
                <select name="gender">
                    <option value="">—</option>
                    <?php foreach ($genderLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($e['gender'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label>الحالة الاجتماعية</label>
            <select name="marital_status">
                <option value="">—</option>
                <?php foreach ($maritalStatusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($e['marital_status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="card-title" style="margin-top:20px;"><span>التواصل والطوارئ</span></div>
        <div class="grid-2">
            <div class="field">
                <label>الجوال</label>
                <input type="text" name="phone" value="<?= e($e['phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label>البريد الشخصي</label>
                <input type="email" name="personal_email" value="<?= e($e['personal_email'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label>العنوان</label>
            <input type="text" name="address" value="<?= e($e['address'] ?? '') ?>">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اسم جهة اتصال الطوارئ</label>
                <input type="text" name="emergency_contact_name" value="<?= e($e['emergency_contact_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>جوال جهة اتصال الطوارئ</label>
                <input type="text" name="emergency_contact_phone" value="<?= e($e['emergency_contact_phone'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label>صلة القرابة</label>
            <input type="text" name="emergency_contact_relation" value="<?= e($e['emergency_contact_relation'] ?? '') ?>">
        </div>

        <div class="card-title" style="margin-top:20px;"><span>المؤهلات</span></div>
        <div class="field">
            <label>المهارات (سطر لكل مهارة)</label>
            <textarea name="skills" rows="3"><?= e($e['skills'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label>اللغات</label>
            <input type="text" name="languages" value="<?= e($e['languages'] ?? '') ?>" placeholder="مثال: العربية، الإنجليزية">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>رقم رخصة القيادة</label>
                <input type="text" name="driving_license_number" value="<?= e($e['driving_license_number'] ?? '') ?>">
            </div>
            <div class="field">
                <label>تاريخ انتهاء رخصة القيادة</label>
                <input type="date" name="driving_license_expiry" value="<?= e($e['driving_license_expiry'] ?? '') ?>">
            </div>
        </div>

        <?php if ($canViewSensitive): ?>
        <div class="card-title" style="margin-top:20px;"><span>🔒 العقد والراتب (بيانات مقيّدة)</span></div>
        <div class="grid-2">
            <div class="field">
                <label>نوع العقد</label>
                <input type="text" name="contract_type" value="<?= e($e['contract_type'] ?? '') ?>">
            </div>
            <div class="field">
                <label>فترة التجربة (تنتهي بتاريخ)</label>
                <input type="date" name="probation_end_date" value="<?= e($e['probation_end_date'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>تاريخ بداية العقد</label>
                <input type="date" name="contract_start_date" value="<?= e($e['contract_start_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label>تاريخ نهاية العقد</label>
                <input type="date" name="contract_end_date" value="<?= e($e['contract_end_date'] ?? '') ?>">
                <p class="hint">يظهر كتنبيه بالتقويم الموحّد قبل الانتهاء.</p>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>الراتب الأساسي</label>
                <input type="number" step="0.01" name="salary_base" value="<?= e($e['salary_base'] ?? '') ?>">
            </div>
            <div class="field">
                <label>البدلات</label>
                <input type="number" step="0.01" name="salary_allowances" value="<?= e($e['salary_allowances'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اسم البنك</label>
                <input type="text" name="bank_name" value="<?= e($e['bank_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>رقم الآيبان</label>
                <input type="text" name="bank_iban" value="<?= e($e['bank_iban'] ?? '') ?>" dir="ltr">
            </div>
        </div>

        <div class="card-title" style="margin-top:20px;"><span>🔒 الأسرة والتأمين (بيانات مقيّدة)</span></div>
        <div class="field">
            <label>رقم التأمينات الاجتماعية (GOSI)</label>
            <input type="text" name="gosi_number" value="<?= e($e['gosi_number'] ?? '') ?>">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>شركة التأمين الطبي</label>
                <input type="text" name="medical_insurance_provider" value="<?= e($e['medical_insurance_provider'] ?? '') ?>">
            </div>
            <div class="field">
                <label>رقم بوليصة التأمين الطبي</label>
                <input type="text" name="medical_insurance_policy_number" value="<?= e($e['medical_insurance_policy_number'] ?? '') ?>">
            </div>
        </div>
        <p class="hint">المُعالون (الأسرة) وشهادات/دورات ومستندات الموظف تُدار من صفحة الملف بعد الحفظ.</p>
        <?php endif; ?>

        <?php if ($canManage ?? false): ?>
        <div class="card-title" style="margin-top:20px;"><span>ملاحظات إدارية</span></div>
        <div class="field">
            <label>ملاحظات خاصة (لا تظهر للموظف نفسه)</label>
            <textarea name="admin_notes" rows="3"><?= e($e['admin_notes'] ?? '') ?></textarea>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الملف' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/employees/' . $e['id']) : route('/employees') ?>">إلغاء</a>
        </div>
    </form>
</div>
