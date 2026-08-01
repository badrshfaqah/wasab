<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Export;
use App\Core\Request;
use App\Core\View;

class ActivityLogController
{
    public function index(): void
    {
        if (!$this->allowed()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return;
        }

        $companyId = Auth::isSystemAdmin() ? null : Auth::companyId();
        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = ActivityLog::paginate($page, 20, $companyId, $filters);

        View::render('activity.index', [
            'pageTitle' => 'سجل العمليات',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'options' => ActivityLog::filterOptions($companyId),
            'isSystemAdmin' => Auth::isSystemAdmin(),
        ]);
    }

    /** تصدير السجل المفلتر كـ CSV للامتثال/التدقيق. */
    public function export(): void
    {
        if (!$this->allowed()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return;
        }

        $companyId = Auth::isSystemAdmin() ? null : Auth::companyId();
        $filters = $this->currentFilters();
        $rows = ActivityLog::forExport($companyId, $filters);

        $headers = ['التاريخ', 'المستخدم', 'العملية', 'النوع', 'المعرّف', 'الوصف'];
        if ($companyId === null) {
            $headers[] = 'الشركة';
        }

        $data = array_map(function (array $r) use ($companyId) {
            $row = [
                $r['created_at'],
                $r['user_name'] ?? 'النظام',
                $r['action'],
                $r['subject_type'] ?? '',
                $r['subject_id'] ?? '',
                $r['description'],
            ];
            if ($companyId === null) {
                $row[] = $r['company_name'] ?? '';
            }
            return $row;
        }, $rows);

        Export::csv('activity-log-' . date('Ymd-His'), $headers, $data);
    }

    private function allowed(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin();
    }

    private function currentFilters(): array
    {
        $filters = [];
        if ($uid = (int) Request::query('user_id', 0)) {
            $filters['user_id'] = $uid;
        }
        if ($action = trim((string) Request::query('action', ''))) {
            $filters['action'] = $action;
        }
        if ($stype = trim((string) Request::query('subject_type', ''))) {
            $filters['subject_type'] = $stype;
        }
        foreach (['date_from', 'date_to'] as $k) {
            $v = trim((string) Request::query($k, ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v)) {
                $filters[$k] = $v;
            }
        }
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if (Auth::isSystemAdmin() && ($cid = (int) Request::query('company_id', 0))) {
            $filters['company_id'] = $cid;
        }
        return $filters;
    }
}
