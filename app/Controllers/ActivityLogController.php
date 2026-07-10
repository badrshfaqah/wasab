<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

class ActivityLogController
{
    public function index(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return;
        }

        $page = max(1, (int) Request::query('page', 1));
        $companyId = Auth::isSystemAdmin() ? null : Auth::companyId();
        $result = ActivityLog::paginate($page, 20, $companyId);

        View::render('activity.index', [
            'pageTitle' => 'سجل العمليات',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
        ]);
    }
}
