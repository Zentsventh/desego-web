<?php

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function admin(): View
    {
        return view('desego.index');
    }

    public function store(): View
    {
        return view('desego.tienda');
    }
}
