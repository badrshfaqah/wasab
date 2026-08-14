<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Employees\Controllers\EmployeeController;
use Modules\Employees\Controllers\EmployeeLeaveController;

/**
 * يُحمَّل فقط عندما تكون إضافة الملف الوظيفي مفعّلة. لا علاقة له بملفات النواة.
 * /employees/create مسجّل قبل /employees/{id} حتى لا يلتقطها الموجّه كمعرّف ملف.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/employees', [EmployeeController::class, 'index'], [$auth]);
    $router->get('/employees/export/{format}', [EmployeeController::class, 'export'], [$auth]);
    $router->get('/employees/expiring', [EmployeeController::class, 'expiring'], [$auth]);
    $router->get('/employees/orgchart', [EmployeeController::class, 'orgChart'], [$auth]);
    $router->get('/employees/create', [EmployeeController::class, 'create'], [$auth]);
    $router->post('/employees', [EmployeeController::class, 'store'], [$auth]);

    // الإجازات والأذونات (مسارات ثابتة قبل /{id})
    $router->get('/employees/leaves', [EmployeeLeaveController::class, 'index'], [$auth]);
    $router->get('/employees/leaves/request', [EmployeeLeaveController::class, 'create'], [$auth]);
    $router->post('/employees/leaves', [EmployeeLeaveController::class, 'store'], [$auth]);
    $router->post('/employees/leaves/{id}/approve', [EmployeeLeaveController::class, 'approve'], [$auth]);
    $router->post('/employees/leaves/{id}/reject', [EmployeeLeaveController::class, 'reject'], [$auth]);

    // الاستيراد الجماعي من CSV
    $router->get('/employees/import', [EmployeeController::class, 'importForm'], [$auth]);
    $router->post('/employees/import', [EmployeeController::class, 'import'], [$auth]);

    $router->get('/employees/{id}', [EmployeeController::class, 'show'], [$auth]);
    $router->get('/employees/{id}/eosb', [EmployeeController::class, 'endOfService'], [$auth]);
    $router->get('/employees/{id}/edit', [EmployeeController::class, 'edit'], [$auth]);
    $router->post('/employees/{id}', [EmployeeController::class, 'update'], [$auth]);
    $router->post('/employees/{id}/delete', [EmployeeController::class, 'destroy'], [$auth]);

    $router->post('/employees/{id}/dependents', [EmployeeController::class, 'addDependent'], [$auth]);
    $router->post('/employees/{id}/dependents/{dependentId}/delete', [EmployeeController::class, 'deleteDependent'], [$auth]);
    $router->post('/employees/{id}/disciplinary', [EmployeeController::class, 'addDisciplinary'], [$auth]);
    $router->post('/employees/{id}/disciplinary/{recordId}/delete', [EmployeeController::class, 'deleteDisciplinary'], [$auth]);
    $router->post('/employees/{id}/reviews', [EmployeeController::class, 'addReview'], [$auth]);
    $router->post('/employees/{id}/reviews/{reviewId}/delete', [EmployeeController::class, 'deleteReview'], [$auth]);

    $router->post('/employees/{id}/certifications', [EmployeeController::class, 'addCertification'], [$auth]);
    $router->post('/employees/{id}/certifications/{certId}/delete', [EmployeeController::class, 'deleteCertification'], [$auth]);

    $router->post('/employees/{id}/documents', [EmployeeController::class, 'uploadDocument'], [$auth]);
    $router->get('/employees/{id}/documents/{docId}', [EmployeeController::class, 'downloadDocument'], [$auth]);
    $router->post('/employees/{id}/documents/{docId}/delete', [EmployeeController::class, 'deleteDocument'], [$auth]);

    $router->post('/employees/{id}/timeline', [EmployeeController::class, 'addTimelineEntry'], [$auth]);
    $router->post('/employees/{id}/timeline/{entryId}/delete', [EmployeeController::class, 'deleteTimelineEntry'], [$auth]);
};
