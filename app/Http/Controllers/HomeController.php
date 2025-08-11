<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        // Protege todo el controlador para que solo acceda un usuario logueado
        $this->middleware('auth');
    }

    public function index()
    {

        return view('home', [
            'usuario' => auth()->user(), 
        ]);
    }
}
