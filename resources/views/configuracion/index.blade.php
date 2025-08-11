@extends('adminlte::page')

@section('title', 'Configuraciones del sistema')

@section('content_header')
    <h1 class="text-center w-100">Configuraciones del sistema</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row g-3 justify-content-center">

    @can('ver roles')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-navy"><i class="bi bi-bookmarks"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Roles</b></span>
          <a href="{{ route('configuracion.roles.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver usuarios')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-orange"><i class="bi bi-people-fill"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Usuarios</b></span>
          <a href="{{ route('configuracion.usuarios.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver vaciar bd')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-danger"><i class="bi bi-trash-fill"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Vaciar Base de datos</b></span>
          <a href="{{ route('configuracion.vaciar-bd.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver eliminar materias')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-danger"><i class="bi bi-trash-fill"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Eliminar Materias</b></span>
          <a href="{{ route('configuracion.eliminar-materias.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver activar usuarios')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-orange"><i class="bi bi-person-x"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Activar/Desactivar Usuarios</b></span>
          <a href="{{ route('configuracion.activar-usuarios.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver estadisticas')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-success"><i class="bi bi-bar-chart-fill"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Estadísticas</b></span>
          <a href="{{ route('configuracion.estadisticas.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver calendario escolar')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-primary"><i class="bi bi-calendar2-week"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Calendario Escolar</b></span>
          <a href="{{ route('configuracion.calendario-escolar.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver horarios pasados')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-info"><i class="bi bi-hourglass-split"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Horarios Pasados</b></span>
          <a href="{{ route('configuracion.horarios-pasados.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver registro actividad')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-gray"><i class="bi bi-clock-history"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Registro de actividades</b></span>
          <a href="{{ route('configuracion.registro-actividad.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

  </div>
</div>
@endsection
