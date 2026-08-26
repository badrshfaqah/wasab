<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Contacts\Controllers\DirectoryController;

return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/contacts', [DirectoryController::class, 'index'], [$auth]);

    // الثابتة قبل ذوات {id}
    $router->get('/contacts/orgs/create', [DirectoryController::class, 'createOrg'], [$auth]);
    $router->post('/contacts/orgs', [DirectoryController::class, 'storeOrg'], [$auth]);
    $router->get('/contacts/people/create', [DirectoryController::class, 'createPerson'], [$auth]);
    $router->post('/contacts/people', [DirectoryController::class, 'storePerson'], [$auth]);
    $router->post('/contacts/link', [DirectoryController::class, 'link'], [$auth]);
    $router->post('/contacts/unlink', [DirectoryController::class, 'unlink'], [$auth]);

    $router->get('/contacts/orgs/{id}', [DirectoryController::class, 'showOrg'], [$auth]);
    $router->post('/contacts/orgs/{id}', [DirectoryController::class, 'updateOrg'], [$auth]);
    $router->post('/contacts/orgs/{id}/archive', [DirectoryController::class, 'archiveOrg'], [$auth]);
    $router->get('/contacts/people/{id}', [DirectoryController::class, 'showPerson'], [$auth]);
    $router->post('/contacts/people/{id}', [DirectoryController::class, 'updatePerson'], [$auth]);
    $router->post('/contacts/people/{id}/archive', [DirectoryController::class, 'archivePerson'], [$auth]);
};
