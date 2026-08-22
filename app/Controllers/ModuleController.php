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

    /** التحديث الذاتي: سحب آخر نسخة من GitHub وتطبيقها (مدير النظام، بضغطة زر). */
    public function selfUpdate(): void
    {
        $this->verifyCsrf();

        $result = \App\Core\SelfUpdater::run();
        flash_set($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/extensions');
    }

    /**
     * «أكمل التحديث»: للمن يسحب ملفات النظام عبر Git الاستضافة مباشرة - يطبّق كل
     * ما تبقى بعد وصول الملفات (ترقيات النواة + ترقيات كل الإضافات + مزامنة
     * الصلاحيات) ويعرض تقريراً واضحاً بما طُبِّق بدل الاعتماد على الصمت التلقائي.
     */
    public function finishUpdate(): void
    {
        $this->verifyCsrf();

        $report = [];
        $errors = [];

        // 1) ترقيات النواة (تتجاهل تهدئة إعادة المحاولة)
        $coreResults = CoreMigrator::applyAll();
        $coreApplied = array_filter($coreResults, fn ($r) => $r['status'] === 'applied');
        foreach (array_filter($coreResults, fn ($r) => $r['status'] === 'failed') as $r) {
            $errors[] = 'نواة: ' . $r['label'] . ' (' . $r['error'] . ')';
        }
        if ($coreApplied) {
            $report[] = 'النواة: ' . count($coreApplied) . ' ترقية';
        }

        // 2) ترقيات الإضافات التي ملفاتها أحدث من قاعدة بياناتها
        $disk = ModuleManager::discover();
        foreach (ModuleManager::installedRows() as $key => $row) {
            if (!isset($disk[$key])) {
                continue;
            }
            $diskVersion = $disk[$key]['version'] ?? '1.0.0';
            if (!version_compare($row['version'], $diskVersion, '<')) {
                continue;
            }
            try {
                ModuleManager::update($key);
                $report[] = ($disk[$key]['name'] ?? $key) . ': ' . $row['version'] . ' ← ' . $diskVersion;
            } catch (\Throwable $e) {
                log_exception($e);
                $errors[] = ($disk[$key]['name'] ?? $key) . ': ' . $e->getMessage();
            }
        }

        $version = \App\Core\Wasab::currentVersion();
        if ($errors) {
            flash_set('error', 'اكتمل مع أخطاء: ' . implode(' | ', $errors) . '. راجع storage/logs/app.log.');
        } elseif ($report) {
            flash_set('success', "✅ اكتمل التحديث — النظام الآن على {$version}. طُبِّق: " . implode('، ', $report) . '.');
        } else {
            flash_set('success', "✅ كل شيء محدّث — النظام على {$version} وقواعد البيانات مطابقة للملفات، لا شيء لتطبيقه.");
        }

        \App\Core\ActivityLog::log('module.finish_update', 'system', null, 'إكمال تحديث بعد سحب Git (' . ($report ? implode('، ', $report) : 'لا تغييرات') . ')');
        redirect('/extensions');
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
