<?php

namespace App\Controllers;

use App\Core\CoreMigrator;
use App\Core\Csrf;
use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\View;

class ModuleController
{
    public function index(): void
    {
        // توكن مهام الجدولة عبر الويب: يولَّد مرة واحدة ويُعرض لمدير النظام فقط.
        $cronToken = (string) \App\Core\Setting::get('cron_web_token', null, '');
        if ($cronToken === '') {
            $cronToken = bin2hex(random_bytes(24));
            \App\Core\Setting::set('cron_web_token', $cronToken);
        }

        View::render('modules.index', [
            'pageTitle' => 'إدارة الإضافات',
            'modules' => ModuleManager::list(),
            'coreSchemaUpToDate' => CoreMigrator::isUpToDate(),
            'cronCliCommand' => 'php ' . BASE_PATH . '/cron.php',
            'cronWebUrl' => route('/cron/' . $cronToken),
        ]);
    }

    /** زر يدوي لمدير النظام: يعيد فحص وتطبيق أي ترقيات ناقصة على جداول النواة، كأنها ترقية إضافة. */
    public function updateDatabase(): void
    {
        $this->verifyCsrf();

        $results = CoreMigrator::applyAll();
        $failed = array_filter($results, fn ($r) => $r['status'] === 'failed');

        if ($failed) {
            $messages = array_map(fn ($r) => $r['label'] . ': ' . $r['error'], $failed);
            flash_set('error', 'فشل تطبيق بعض ترقيات النواة: ' . implode(' | ', $messages) . '. راجع storage/logs/app.log.');
        } elseif (array_filter($results, fn ($r) => $r['status'] === 'applied')) {
            flash_set('success', 'تم تحديث النواة بنجاح.');
        } else {
            flash_set('success', 'النواة محدّثة بالفعل، لا يوجد شيء لتطبيقه.');
        }

        redirect('/extensions');
    }

    public function install(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::install($params['key']);
            flash_set('success', 'تم تثبيت الإضافة بنجاح. يمكنك الآن تفعيلها.');
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر تثبيت الإضافة. راجع سجل الأخطاء.');
        }
        redirect('/extensions');
    }

    public function activate(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::activate($params['key']);
            flash_set('success', 'تم تفعيل الإضافة.');
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر تفعيل الإضافة. راجع سجل الأخطاء.');
        }
        redirect('/extensions');
    }

    public function deactivate(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::deactivate($params['key']);
            flash_set('success', 'تم تعطيل الإضافة. بياناتها محفوظة ولن تظهر حتى تُفعّل مجدداً.');
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر تعطيل الإضافة. راجع سجل الأخطاء.');
        }
        redirect('/extensions');
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::update($params['key']);
            flash_set('success', 'تم تحديث الإضافة.');
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر تحديث الإضافة. راجع سجل الأخطاء.');
        }
        redirect('/extensions');
    }

    public function remove(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::remove($params['key']);
            flash_set('success', 'تمت إزالة الإضافة وبياناتها الخاصة بها.');
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر إزالة الإضافة. راجع سجل الأخطاء.');
        }
        redirect('/extensions');
    }

    public function moveUp(array $params): void
    {
        $this->verifyCsrf();
        ModuleManager::moveUp($params['key']);
        redirect('/extensions');
    }

    public function moveDown(array $params): void
    {
        $this->verifyCsrf();
        ModuleManager::moveDown($params['key']);
        redirect('/extensions');
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/extensions');
        }
    }
}
