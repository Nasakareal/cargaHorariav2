@extends('adminlte::page')

@section('title', 'Crear Programa')

@section('content_header')
  <h1 class="text-center w-100">Creación de un nuevo programa</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Llene los datos</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('programas.store') }}" method="POST" autocomplete="off">
            @csrf

            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <label for="program_name">Nombre del programa</label>
                  <input type="text"
                         name="program_name"
                         id="program_name"
                         class="form-control"
                         value="{{ old('program_name') }}"
                         required
                         maxlength="255"
                         placeholder="Ej. TECNOLOGÍAS DE LA INFORMACIÓN">
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label for="area">Área</label>
                  <input type="text"
                         name="area"
                         id="area"
                         class="form-control"
                         value="{{ old('area') }}"
                         maxlength="255"
                         list="listaAreas"
                         placeholder="Ej. TI">
                  @if (!empty($areas))
                    <datalist id="listaAreas">
                      @foreach ($areas as $a)
                        <option value="{{ $a }}"></option>
                      @endforeach
                    </datalist>
                  @endif
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-primary">Registrar</button>
                  <a href="{{ route('programas.index') }}" class="btn btn-secondary">Cancelar</a>
                </div>
              </div>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if ($errors->any())
<script>
Swal.fire({
  icon: 'warning',
  title: 'Revisa los datos',
  html: `{!! implode('<br>', $errors->all()) !!}`,
  position: 'center'
});
</script>
@endif

{{-- (Opcional) Forzar mayúsculas al perder foco --}}
<script>
['program_name','area'].forEach(id=>{
  const el = document.getElementById(id);
  if (el) el.addEventListener('blur', ()=> el.value = (el.value||'').toUpperCase().trim());
});
</script>
@endsection
