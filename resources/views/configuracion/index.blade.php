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
        <span class="info-box-icon bg-orange">
          <i id="toggle-icon" class="bi bi-person-x"></i>
        </span>
        <div class="info-box-content">
          <span class="info-box-text"><b id="toggle-text">Activar Usuarios</b></span>

          {{-- Toggle Switch iOS-like --}}
          <label class="switch">
            <input type="checkbox"
                   id="toggle-switch"
                   data-url-on="{{ route('configuracion.activar-usuarios.on') }}"
                   data-url-off="{{ route('configuracion.activar-usuarios.off') }}">
            <span class="slider"></span>
          </label>
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


{{-- CSS mínimo del switch (tipo iOS) --}}
<style>
.switch{position:relative;display:inline-block;width:58px;height:30px}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:9999px;transition:.25s}
.slider:before{content:"";position:absolute;height:24px;width:24px;left:3px;top:3px;background:#fff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:.25s}
input:checked + .slider{background:linear-gradient(135deg,#34d399,#10b981)}
input:checked + .slider:before{transform:translateX(28px)}
</style>

{{-- JS: igual al tuyo, pero usando rutas de Laravel --}}
<script>
document.addEventListener("DOMContentLoaded", function () {
  const toggleSwitch = document.getElementById("toggle-switch");
  const icon = document.getElementById("toggle-icon");
  const text = document.getElementById("toggle-text");

  const urlOn  = toggleSwitch.dataset.urlOn;
  const urlOff = toggleSwitch.dataset.urlOff;

  // Cargar estado guardado (solo UI)
  const estadoGuardado = localStorage.getItem("estadoUsuarios");
  if (estadoGuardado === "activar") {
      toggleSwitch.checked = true;
      icon.className = "bi bi-person-fill-check";
      text.textContent = "Desactivar Usuarios";
  } else {
      toggleSwitch.checked = false;
      icon.className = "bi bi-person-x";
      text.textContent = "Activar Usuarios";
  }

  // Guardar estado y redirigir
  toggleSwitch.addEventListener("change", function () {
      if (this.checked) {
          icon.className = "bi bi-person-fill-check";
          text.textContent = "Desactivar Usuarios";
          localStorage.setItem("estadoUsuarios", "activar");
          window.location.href = urlOn;
      } else {
          icon.className = "bi bi-person-x";
          text.textContent = "Activar Usuarios";
          localStorage.setItem("estadoUsuarios", "desactivar");
          window.location.href = urlOff;
      }
  });
});
</script>
