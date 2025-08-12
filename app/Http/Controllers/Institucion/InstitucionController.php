<?php

namespace App\Http\Controllers\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InstitucionController extends Controller
{
    public function index()
    {
        return view('institucion.index');
    }
}
