@extends('adminlte::page')

@section('title','Archivar Horarios')

@section('content_header')
  <h1 class="text-center w-100">Archivar horarios actuales</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-danger">
        <div class="card-header">
          <h3 class="card-title">Confirmación</h3>
        </div>
        <div class="card-body">
          <p class="mb-2">
            Esto copiará <b>{{ $actuales }}</b> registro(s) desde <code>schedule_assignments</code> a <code>schedule_history</code>
            y luego vaciará <code>schedule_assignments</code> para el nuevo periodo.
          </p>

          <form action="{{ route('configuracion.horarios-pasados.store') }}" method="POST" class="mt-3">
            @csrf
            <div class="mb-3">
              <label class="form-label">Nombre del cuatrimestre (en inglés) — opcional</label>
              <input type="text" name="quarter_name_en" class="form-control" maxlength="255" placeholder="Ej. SPRING 2025">
            </div>

            <input type="hidden" name="confirm" value="1">

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-archive"></i> Archivar ahora
              </button>
              <a href="{{ route('configuracion.horarios-pasados.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>
@endsection
