@extends('adminlte::page')

@section('title', 'Institución')

@section('content_header')
    <h1 class="text-center w-100">Institución</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row g-3 justify-content-center">

    @can('ver salones')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-primary"><i class="bi bi-door-open"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Salones</b></span>
          <a href="{{ route('institucion.salones.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver edificios')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-success"><i class="bi bi-building"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Edificios</b></span>
          <a href="{{ route('institucion.edificios.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

    @can('ver laboratorios')
    <div class="col-12 col-md-6 col-xl-4">
      <div class="info-box">
        <span class="info-box-icon bg-warning"><i class="bi bi-buildings-fill"></i></span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Laboratorios</b></span>
          <a href="{{ route('institucion.laboratorios.index') }}" class="btn btn-primary btn-sm">Acceder</a>
        </div>
      </div>
    </div>
    @endcan

  </div>
</div>
@endsection
