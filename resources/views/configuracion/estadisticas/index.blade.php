{{-- resources/views/configuracion/estadisticas/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Estadísticas del Sistema')

@section('content_header')
  <h1 class="text-center w-100">Estadísticas del Sistema</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">

    {{-- Horarios de Grupos --}}
    <div class="col-md-4 col-sm-6 col-12">
      <div class="info-box">
        <span class="info-box-icon bg-purple">
          <i class="bi bi-calendar4-week"></i>
        </span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Horarios de Grupos</b></span>
          <a  href="{{ route('configuracion.estadisticas.export.grupos') }}"
              class="btn btn-primary btn-sm dl-btn"
              data-label="Descargar ZIP">
            <span class="btn-label">Descargar ZIP</span>
            <span class="spinner-border spinner-border-sm d-none align-text-bottom" role="status" aria-hidden="true"></span>
          </a>
          <small class="text-muted d-block mt-1">Genera un ZIP con un Excel por grupo.</small>
        </div>
      </div>
    </div>

    {{-- Horarios de Grupos sin Profesor --}}
    <div class="col-md-4 col-sm-6 col-12">
      <div class="info-box">
        <span class="info-box-icon bg-indigo">
          <i class="bi bi-exclamation-triangle"></i>
        </span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Grupos sin Profesor</b></span>
          <a  href="{{ route('configuracion.estadisticas.export.grupos-sin-profesor') }}"
              class="btn btn-primary btn-sm dl-btn"
              data-label="Descargar ZIP">
            <span class="btn-label">Descargar ZIP</span>
            <span class="spinner-border spinner-border-sm d-none align-text-bottom" role="status" aria-hidden="true"></span>
          </a>
          <small class="text-muted d-block mt-1">ZIP con plantillas marcando “SIN PROFESOR”.</small>
        </div>
      </div>
    </div>

    {{-- Horarios de Profesores --}}
    <div class="col-md-4 col-sm-6 col-12">
      <div class="info-box">
        <span class="info-box-icon bg-lightblue">
          <i class="bi bi-person-video3"></i>
        </span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Horarios de Profesores</b></span>
          <a  href="{{ route('configuracion.estadisticas.export.profesores') }}"
              class="btn btn-primary btn-sm dl-btn"
              data-label="Descargar ZIP">
            <span class="btn-label">Descargar ZIP</span>
            <span class="spinner-border spinner-border-sm d-none align-text-bottom" role="status" aria-hidden="true"></span>
          </a>
          <small class="text-muted d-block mt-1">ZIP con un Excel por profesor.</small>
        </div>
      </div>
    </div>

    {{-- (Opcional) Otros mosaicos futuros --}}
    <div class="col-md-4 col-sm-6 col-12">
      <div class="info-box">
        <span class="info-box-icon bg-info">
          <i class="bi bi-pie-chart-fill"></i>
        </span>
        <div class="info-box-content">
          <span class="info-box-text"><b>Suficiencia Carga Horaria</b></span>
          <button class="btn btn-secondary btn-sm" disabled>Próximamente</button>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  // Añade un pequeño spinner y evita clics repetidos mientras descarga
  document.querySelectorAll('.dl-btn').forEach(function(btn){
    btn.addEventListener('click', function(e){
      // Si ya está “cargando”, no hagas nada
      if (btn.dataset.loading === '1') return;

      // Confirmación opcional (descomenta si quieres)
      // e.preventDefault();
      // Swal.fire({
      //   title: 'Generar ZIP',
      //   text: 'Se generará un archivo ZIP con múltiples Excel. ¿Continuar?',
      //   icon: 'question',
      //   showCancelButton: true,
      //   confirmButtonText: 'Sí, descargar',
      //   cancelButtonText: 'Cancelar'
      // }).then((r) => {
      //   if (r.isConfirmed) window.location.href = btn.href;
      // });

      // Estado visual
      const label   = btn.querySelector('.btn-label');
      const spinner = btn.querySelector('.spinner-border');
      if (label && spinner) {
        label.textContent = 'Preparando...';
        spinner.classList.remove('d-none');
      }
      btn.classList.add('disabled');
      btn.dataset.loading = '1';

      // Cuando el navegador cambie de página (descarga iniciada), resetea
      window.addEventListener('pageshow', function reset(){
        btn.classList.remove('disabled');
        btn.dataset.loading = '0';
        if (label && spinner) {
          label.textContent = btn.getAttribute('data-label') || 'Descargar ZIP';
          spinner.classList.add('d-none');
        }
        window.removeEventListener('pageshow', reset);
      });
    }, { passive: true });
  });

  // Flashes
  @if (session('success'))
    Swal.fire({ icon:'success', title:@json(session('success')), timer:4500, showConfirmButton:false, position:'center' });
  @endif
  @if (session('error'))
    Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), position:'center' });
  @endif
})();
</script>
@endsection
