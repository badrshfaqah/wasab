<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Workspace;

/**
 * استيراد الجهات وجهات الاتصال من CSV وتصديرها.
 *
 * الاستيراد على ثلاث خطوات مقصودة: رفع الملف، ثم معاينة صفوفه ومطابقة أعمدته
 * بحقول النظام مع كشف المكرر مقارنةً بالدليل المركزي، ثم التنفيذ - فلا تدخل
 * بيانات لم يرها المستخدم، ولا تتضاعف جهة موجودة أصلاً.
 */
class ImportController extends BaseCrmController
{
    /** الحقول التي يمكن مطابقة أعمدة الملف بها. */
    private const FIELDS = [
        'name' => 'اسم الجهة (مطلوب)',
        'trade_name' => 'الاسم التجاري',
        'sector' => 'القطاع',
        'city' => 'المدينة',
        'country' => 'الدولة',
        'address' => 'العنوان',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'website' => 'الموقع الإلكتروني',
        'notes' => 'ملاحظات',
        'contact_name' => 'اسم شخص التواصل',
        'contact_job' => 'مسمى شخص التواصل',
        'contact_mobile' => 'جوال شخص التواصل',
        'contact_email' => 'بريد شخص التواصل',
    ];

    public function form(array $params): void
    {
        [$workspace, $membership] = $this->guard($params, 'orgs.create');

        View::render('crm::import', [
            'pageTitle' => 'استيراد جهات — ' . $workspace['name'],
            'workspace' => $workspace,
            'step' => 'upload',
            'fields' => self::FIELDS,
            'canExport' => Workspace::can($membership, 'export'),
        ]);
    }

    /** الخطوة 2: قراءة الملف وعرض معاينة ومطابقة أعمدة واكتشاف المكرر. */
    public function preview(array $params): void
    {
        [$workspace, $membership] = $this->guard($params, 'orgs.create');
        $companyId = (int) $workspace['company_id'];
        $back = '/crm/w/' . $workspace['id'] . '/import';
        $this->verifyCsrf($back);

        $file = Request::file('csv');
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            flash_set('error', 'اختر ملف CSV صالحاً.');
            redirect($back);
        }
        if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
            flash_set('error', 'الحد الأقصى لحجم الملف 3 ميجابايت.');
            redirect($back);
        }

        $dir = BASE_PATH . '/storage/uploads/crm/imports/' . $companyId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            flash_set('error', 'تعذّر إنشاء مجلد الاستيراد على الخادم.');
            redirect($back);
        }
        $token = bin2hex(random_bytes(12));
        $path = $dir . '/' . $token . '.csv';
        if (!@move_uploaded_file($file['tmp_name'], $path)) {
            flash_set('error', 'تعذّر حفظ الملف المرفوع.');
            redirect($back);
        }

        [$header, $rows] = $this->readCsv($path, 2000);
        if (!$header) {
            @unlink($path);
            flash_set('error', 'الملف فارغ أو غير مقروء.');
            redirect($back);
        }

        // مطابقة أولية بأسماء الأعمدة الشائعة (عربية وإنجليزية)
        $guesses = [];
        foreach ($header as $i => $col) {
            $guesses[$i] = $this->guessField((string) $col);
        }
        // كشف المكرر بالاسم على عمود الاسم المُخمَّن
        $nameIndex = array_search('name', $guesses, true);
        $duplicates = 0;
        if ($nameIndex !== false) {
            foreach (array_slice($rows, 0, 50) as $row) {
                $value = trim((string) ($row[$nameIndex] ?? ''));
                if ($value !== '' && Organization::possibleDuplicates($companyId, $value)) {
                    $duplicates++;
                }
            }
        }

        View::render('crm::import', [
            'pageTitle' => 'مطابقة أعمدة الاستيراد',
            'workspace' => $workspace,
            'step' => 'map',
            'fields' => self::FIELDS,
            'header' => $header,
            'rows' => array_slice($rows, 0, 10),
            'total' => count($rows),
            'guesses' => $guesses,
            'token' => $token,
            'duplicates' => $duplicates,
            'canExport' => Workspace::can($membership, 'export'),
        ]);
    }

    /** الخطوة 3: التنفيذ حسب المطابقة، مع تقرير بالنتيجة. */
    public function run(array $params): void
    {
        [$workspace, ] = $this->guard($params, 'orgs.create');
        $companyId = (int) $workspace['company_id'];
        $back = '/crm/w/' . $workspace['id'] . '/import';
        $this->verifyCsrf($back);

        $token = preg_replace('/[^a-f0-9]/', '', (string) Request::input('token', ''));
        $path = BASE_PATH . '/storage/uploads/crm/imports/' . $companyId . '/' . $token . '.csv';
        if ($token === '' || !is_file($path)) {
            flash_set('error', 'انتهت صلاحية الملف المرفوع — أعد رفعه.');
            redirect($back);
        }

        $mapping = array_map('strval', (array) Request::input('map', []));
        if (!in_array('name', $mapping, true)) {
            flash_set('error', 'حدد العمود الذي يحمل اسم الجهة.');
            redirect($back);
        }
        $onDuplicate = Request::input('on_duplicate') === 'update' ? 'update' : 'skip';

        [, $rows] = $this->readCsv($path, 2000);
        $created = $updated = $skipped = $linked = $contacts = 0;

        foreach ($rows as $row) {
            $data = [];
            foreach ($mapping as $index => $field) {
                if ($field === '' || !isset(self::FIELDS[$field])) {
                    continue;
                }
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $data[$field] = $value;
                }
            }
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }

            $existing = Database::first(
                'SELECT * FROM crm_organizations WHERE company_id = :c AND name = :n LIMIT 1',
                ['c' => $companyId, 'n' => $name]
            );

            if ($existing && $onDuplicate === 'skip') {
                // موجودة: نربطها بالمساحة دون تعديل بياناتها
                $relationId = Organization::link((int) $workspace['id'], (int) $existing['id'], ['added_by' => Auth::id(), 'owner_id' => Auth::id()]);
                $linked++;
                $contacts += $this->importContact($companyId, (int) $existing['id'], $data);
                continue;
            }

            $payload = array_intersect_key($data, array_flip(['name', 'trade_name', 'sector', 'city', 'country', 'address', 'email', 'phone', 'website', 'notes']));
            if ($existing) {
                Organization::update((int) $existing['id'], $payload);
                $organizationId = (int) $existing['id'];
                $updated++;
            } else {
                $organizationId = Organization::create($payload + ['company_id' => $companyId, 'created_by' => Auth::id()]);
                $created++;
            }
            Organization::link((int) $workspace['id'], $organizationId, ['added_by' => Auth::id(), 'owner_id' => Auth::id()]);
            $contacts += $this->importContact($companyId, $organizationId, $data);
        }

        @unlink($path);
        CrmLog::add((int) $workspace['id'], 'import', 'workspace', (int) $workspace['id'],
            "استيراد CSV: {$created} جديدة، {$updated} محدَّثة، {$linked} مرتبطة، {$skipped} متجاهلة");

        flash_set('success', "اكتمل الاستيراد — أُضيفت {$created} جهة، حُدّثت {$updated}، رُبطت {$linked} موجودة مسبقاً، وأُضيف {$contacts} شخص تواصل"
            . ($skipped ? "، وتُجوهلت {$skipped} صفوف بلا اسم." : '.'));
        redirect('/crm/w/' . $workspace['id']);
    }

    /** تصدير جهات المساحة CSV - لمن يملك صلاحية التصدير. */
    public function export(array $params): void
    {
        [$workspace, ] = $this->guard($params, 'export');
        $rows = Organization::inWorkspace((int) $workspace['id'], [], 5000);

        CrmLog::add((int) $workspace['id'], 'export', 'workspace', (int) $workspace['id'], 'تصدير جهات المساحة (' . count($rows) . ')');

        $filename = 'crm-' . preg_replace('/[^\w\-]+/u', '-', $workspace['name']) . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM ليفتح Excel العربية صحيحة
        fputcsv($out, ['الجهة', 'الاسم التجاري', 'القطاع', 'المدينة', 'الدولة', 'البريد', 'الهاتف', 'التصنيفات', 'المسؤول', 'آخر تواصل', 'المتابعة القادمة']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['name'], $r['trade_name'] ?? '', $r['sector'] ?? '', $r['city'] ?? '', $r['country'] ?? '',
                $r['email'] ?? '', $r['phone'] ?? '', $r['categories'] ?? '', $r['owner_name'] ?? '',
                $r['last_activity_at'] ?? '', $r['next_action_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // ---------------------------------------------------------------

    private function guard(array $params, string $ability): array
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, $ability);
        return [$workspace, $membership];
    }

    /** يقرأ CSV ويعيد [العناوين، الصفوف] مع دعم الفاصلة والفاصلة المنقوطة. */
    private function readCsv(string $path, int $limit): array
    {
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return [[], []];
        }
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            return [[], []];
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = str_getcsv(rtrim($first, "\r\n"), $delimiter);

        $rows = [];
        while (count($rows) < $limit && ($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = $line;
        }
        fclose($handle);
        return [$header, $rows];
    }

    /** تخمين الحقل من اسم العمود (عربي/إنجليزي). */
    private function guessField(string $column): string
    {
        $c = mb_strtolower(trim($column));
        // الأكثر تحديداً أولاً: «جوال المسؤول» يجب ألا يُطابق «المسؤول» (اسم الشخص)
        $map = [
            'contact_mobile' => ['جوال المسؤول', 'جوال الشخص', 'جوال شخص التواصل', 'contact mobile', 'contact phone'],
            'contact_email' => ['بريد المسؤول', 'بريد شخص التواصل', 'contact email'],
            'contact_job' => ['مسمى شخص التواصل', 'مسمى المسؤول', 'المسمى', 'الوظيفة', 'job title', 'position'],
            'contact_name' => ['اسم شخص التواصل', 'شخص التواصل', 'جهة الاتصال', 'اسم المسؤول', 'contact name', 'contact person'],
            'trade_name' => ['الاسم التجاري', 'trade name', 'brand'],
            'name' => ['اسم الجهة', 'الجهة', 'الشركة', 'المنشأة', 'organization', 'company', 'name', 'الاسم'],
            'sector' => ['القطاع', 'النشاط', 'sector', 'industry'],
            'city' => ['المدينة', 'city'],
            'country' => ['الدولة', 'country'],
            'address' => ['العنوان', 'address'],
            'email' => ['البريد', 'الايميل', 'الإيميل', 'email', 'e-mail'],
            'phone' => ['الهاتف', 'الجوال', 'رقم التواصل', 'phone', 'mobile', 'tel'],
            'website' => ['الموقع', 'website', 'url', 'site'],
            'notes' => ['ملاحظات', 'notes', 'note'],
        ];

        // مطابقة تامة أولاً، ثم احتواء - فلا يبتلع عمودٌ عامٌّ عموداً أدق
        foreach ($map as $field => $needles) {
            foreach ($needles as $needle) {
                if ($c === mb_strtolower($needle)) {
                    return $field;
                }
            }
        }
        foreach ($map as $field => $needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($c, mb_strtolower($needle))) {
                    return $field;
                }
            }
        }
        return '';
    }

    /** ينشئ شخص تواصل من أعمدة الصف إن وُجد اسمه ولم يكن مسجّلاً. */
    private function importContact(int $companyId, int $organizationId, array $data): int
    {
        $name = trim((string) ($data['contact_name'] ?? ''));
        if ($name === '') {
            return 0;
        }
        $exists = Database::first(
            'SELECT id FROM crm_contacts WHERE organization_id = :o AND name = :n',
            ['o' => $organizationId, 'n' => $name]
        );
        if ($exists) {
            return 0;
        }
        Contact::create([
            'company_id' => $companyId,
            'organization_id' => $organizationId,
            'name' => mb_substr($name, 0, 150),
            'job_title' => mb_substr((string) ($data['contact_job'] ?? ''), 0, 150) ?: null,
            'mobile' => mb_substr((string) ($data['contact_mobile'] ?? ''), 0, 50) ?: null,
            'email' => mb_substr((string) ($data['contact_email'] ?? ''), 0, 150) ?: null,
            'created_by' => Auth::id(),
        ]);
        return 1;
    }
}
