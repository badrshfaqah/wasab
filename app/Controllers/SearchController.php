<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ModuleManager;
use App\Core\View;

class SearchController
{
    private const MIN_LENGTH = 2;

    public function index(): void
    {
        $user = Auth::user();
        $query = trim((string) \App\Core\Request::query('q', ''));

        $results = [];
        if (mb_strlen($query) >= self::MIN_LENGTH) {
            $results = ModuleManager::collectSearchResults($user, $query);
        }

        View::render('search.index', [
            'pageTitle' => 'نتائج البحث',
            'query' => $query,
            'results' => $results,
            'minLength' => self::MIN_LENGTH,
        ]);
    }
}
