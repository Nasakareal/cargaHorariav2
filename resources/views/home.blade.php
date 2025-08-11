@extends('adminlte::page')

@section('title', 'Home')

@section('content_header')
    <h1>Bienvenido {{ $usuario->nombres }}</h1>
@stop

@section('content')
    <p>Estás autenticado correctamente usando la tabla <strong>usuarios</strong>.</p>

@stop
