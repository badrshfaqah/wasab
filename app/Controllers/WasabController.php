<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Wasab;

class WasabController
{
    public function index(): void
    {
        View::render('wasab.index', [
            'pageTitle' => 'التحديثات والإصدارات',
            'changelog' => Wasab::changelog(),
        ]);
    }
}
