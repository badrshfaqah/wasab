<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\View;

class ModuleController
{
    public function index(): void
    {
        View::render('modules.index', [
            'pageTitle' => 'إدارة الإضافات',
            'modules' => ModuleManager::list(),
        ]);
    }

    public function install(array $params): void
    {
        $this->verifyCsrf();
        try {
            ModuleManager::install($params['key']);
            flash_set('success', 'تم تثبيت الإضافة بنجاح. يمكنك الآن تفعيلها.');
        } catch (\Throwable $e) {
            flash_set('error', 'تعذر تثبيت الإضافة: ' . $e->getMessage());
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
            flash_set('error', 'تعذر تفعيل الإضافة: ' . $e->getMessage());
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
            flash_set('error', 'تعذر تعطيل الإضافة: ' . $e->getMessage());
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
            flash_set('error', 'تعذر تحديث الإضافة: ' . $e->getMessage());
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
            flash_set('error', 'تعذر إزالة الإضافة: ' . $e->getMessage());
        }
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
