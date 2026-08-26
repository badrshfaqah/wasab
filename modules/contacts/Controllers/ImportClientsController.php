<?php

namespace Modules\Contacts\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Contacts\Models\Directory;

/**
 * استيراد سجلات إضافة «العملاء» القديمة إلى الدليل مرة واحدة.
 *
 * العميل في وصاب لم يكن إلا جهة صفتها عميل: الشركة تصبح جهة، والفرد يصبح فرداً،
 * وجهة التواصل المذكورة في سجل العميل تصبح شخصاً مرتبطاً بالجهة. لا يُحذف شيء من
 * الإضافة القديمة، والاستيراد قابل للتكرار بأمان (يتجاهل ما استورده سابقاً).
 */
class ImportClientsController
{
    /**
     * هل بقيت بيانات عملاء قديمة تستحق الاستيراد؟ نفحص الجدول نفسه لا الإضافة،
     * فقد تُحذف الإضافة القديمة من النظام ويبقى جدولها بسجلاته في قاعدة قائمة.
     */
    public static function legacyAvailable(int $companyId): bool
    {
        $exists = \App\Core\Database::first(
            "SELECT 1 AS x FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'clients_clients'"
        );
        if (!$exists) {
            return false;
        }
        $count = \App\Core\Database::first('SELECT COUNT(*) AS c FROM clients_clients WHERE company_id = :c', ['c' => $companyId]);
        return (int) ($count['c'] ?? 0) > 0;
    }

    public function form(): void
    {
        $companyId = $this->guard();
        $available = self::legacyAvailable($companyId);
        $clients = [];
        $alreadyIn = 0;

        if ($available) {
            $clients = Database::select(
                "SELECT * FROM clients_clients WHERE company_id = :c ORDER BY name",
                ['c' => $companyId]
            );
            foreach ($clients as $i => $client) {
                $isCompany = ($client['type'] ?? 'company') === 'company';
                $exists = $isCompany
                    ? Database::first('SELECT id FROM contacts_organizations WHERE company_id = :c AND name = :n', ['c' => $companyId, 'n' => $client['name']])
                    : Database::first('SELECT id FROM contacts_persons WHERE company_id = :c AND full_name = :n', ['c' => $companyId, 'n' => $client['name']]);
                $clients[$i]['already'] = (bool) $exists;
                $clients[$i]['is_company'] = $isCompany;
                if ($exists) {
                    $alreadyIn++;
                }
            }
        }

        View::render('contacts::import_clients', [
            'pageTitle' => 'استيراد العملاء إلى الدليل',
            'available' => $available,
            'clients' => $clients,
            'alreadyIn' => $alreadyIn,
        ]);
    }

    public function run(): void
    {
        $companyId = $this->guard();
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/contacts/import-clients');
        }
        if (!self::legacyAvailable($companyId)) {
            flash_set('error', 'لا توجد سجلات عملاء قديمة لاستيرادها.');
            redirect('/contacts');
        }

        $orgs = $people = $links = $skipped = 0;
        foreach (Database::select("SELECT * FROM clients_clients WHERE company_id = :c", ['c' => $companyId]) as $client) {
            // نوع العميل في الإضافة القديمة: company أو person
            $isCompany = ($client['type'] ?? 'company') === 'company';
            $name = trim((string) $client['name']);
            if ($name === '') {
                $skipped++;
                continue;
            }

            if ($isCompany) {
                $existing = Database::first(
                    'SELECT id FROM contacts_organizations WHERE company_id = :c AND name = :n',
                    ['c' => $companyId, 'n' => $name]
                );
                if ($existing) {
                    $skipped++;
                    continue;
                }
                $orgId = Directory::createOrg([
                    'company_id' => $companyId,
                    'name' => mb_substr($name, 0, 200),
                    'kind' => 'شركة',
                    'address' => $client['address'] ?: null,
                    'email' => $client['email'] ?: null,
                    'phone' => $client['phone'] ?: null,
                    'notes' => $client['notes'] ?: null,
                    'status' => ($client['status'] ?? 'active') === 'active' ? 'active' : 'archived',
                    'created_by' => $client['created_by'] ?? Auth::id(),
                ]);
                $orgs++;

                // جهة التواصل المذكورة في سجل العميل تصبح شخصاً مرتبطاً
                $contactName = trim((string) ($client['contact_name'] ?? ''));
                if ($contactName !== '') {
                    $person = Database::first(
                        'SELECT id FROM contacts_persons WHERE company_id = :c AND full_name = :n',
                        ['c' => $companyId, 'n' => $contactName]
                    );
                    $personId = $person['id'] ?? Directory::createPerson([
                        'company_id' => $companyId,
                        'full_name' => mb_substr($contactName, 0, 150),
                        'mobile' => $client['phone'] ?: null,
                        'email' => $client['email'] ?: null,
                        'created_by' => Auth::id(),
                    ]);
                    if (!isset($person['id'])) {
                        $people++;
                    }
                    Directory::link((int) $personId, $orgId, null, null, true);
                    $links++;
                }
            } else {
                $existing = Database::first(
                    'SELECT id FROM contacts_persons WHERE company_id = :c AND full_name = :n',
                    ['c' => $companyId, 'n' => $name]
                );
                if ($existing) {
                    $skipped++;
                    continue;
                }
                Directory::createPerson([
                    'company_id' => $companyId,
                    'full_name' => mb_substr($name, 0, 150),
                    'mobile' => $client['phone'] ?: null,
                    'email' => $client['email'] ?: null,
                    'city' => null,
                    'notes' => $client['notes'] ?: null,
                    'status' => ($client['status'] ?? 'active') === 'active' ? 'active' : 'archived',
                    'created_by' => $client['created_by'] ?? Auth::id(),
                ]);
                $people++;
            }
        }

        ActivityLog::log('contacts.import_clients', 'contacts', null, "استيراد العملاء إلى الدليل: {$orgs} جهة و{$people} فرد");
        flash_set('success', "اكتمل الاستيراد — أُضيفت {$orgs} جهة و{$people} فرد"
            . ($links ? " و{$links} ارتباط" : '')
            . ($skipped ? "، وتُجوهل {$skipped} سجلاً موجوداً مسبقاً." : '.')
            . ' سجلات «العملاء» لم تُحذف — يمكنك تعطيل الإضافة القديمة متى شئت.');
        redirect('/contacts');
    }

    private function guard(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId || !Permission::check('contacts.create')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
        return $companyId;
    }
}
