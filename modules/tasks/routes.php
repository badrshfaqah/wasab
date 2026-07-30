<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Tasks\Controllers\TaskController;

/**
 * يُحمَّل فقط عندما تكون إضافة المهام مفعّلة. لا علاقة له بملفات النواة.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/tasks', [TaskController::class, 'index'], [$auth]);
    $router->get('/tasks/export/{format}', [TaskController::class, 'export'], [$auth]);
    $router->get('/tasks/board', [TaskController::class, 'board'], [$auth]);
    $router->get('/tasks/create', [TaskController::class, 'create'], [$auth]);
    $router->post('/tasks', [TaskController::class, 'store'], [$auth]);
    $router->get('/tasks/{id}', [TaskController::class, 'show'], [$auth]);
    $router->get('/tasks/{id}/edit', [TaskController::class, 'edit'], [$auth]);
    $router->post('/tasks/{id}', [TaskController::class, 'update'], [$auth]);
    $router->post('/tasks/{id}/delete', [TaskController::class, 'destroy'], [$auth]);
    $router->post('/tasks/{id}/status', [TaskController::class, 'changeStatus'], [$auth]);
    $router->post('/tasks/{id}/approve', [TaskController::class, 'approve'], [$auth]);
    $router->post('/tasks/{id}/comments', [TaskController::class, 'comment'], [$auth]);
    $router->post('/tasks/{id}/subtasks', [TaskController::class, 'addSubtask'], [$auth]);
    $router->post('/tasks/{id}/subtasks/{subtaskId}/toggle', [TaskController::class, 'toggleSubtask'], [$auth]);
    $router->post('/tasks/{id}/subtasks/{subtaskId}/delete', [TaskController::class, 'deleteSubtask'], [$auth]);
    $router->post('/tasks/{id}/attachments', [TaskController::class, 'uploadAttachment'], [$auth]);
    $router->get('/tasks/{id}/attachments/{attachmentId}', [TaskController::class, 'downloadAttachment'], [$auth]);
};
