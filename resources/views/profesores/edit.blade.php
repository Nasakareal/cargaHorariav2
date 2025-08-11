@extends('adminlte::page')

@section('title', 'Editar Profesor')

@section('content_header')
  <h1 class="text-center w-100">Edición de profesor</h1>
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

      <div class="card card-outline card-success">
        <div class="card-header">
          <h3 class="card-title">Actualiza los datos</h3>
        </div>

        <div class="card-body">
          <form id="editForm" action="{{ route('profesores.update', $profesor->teacher_id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="row">
              {{-- Nombre --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="teacher_name">Nombres del profesor</label>
                  <input type="text" name="teacher_name" id="teacher_name" class="form-control"
                         value="{{ old('teacher_name', $profesor->teacher_name) }}" required>
                </div>
              </div>

              {{-- Clasificación --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="clasificacion">Clasificación</label>
                  @php $clas = old('clasificacion', $profesor->clasificacion); @endphp
                  <select name="clasificacion" id="clasificacion" class="form-control" required>
                    <option value="PTC" {{ $clas==='PTC' ? 'selected' : '' }}>PTC</option>
                    <option value="PA"  {{ $clas==='PA'  ? 'selected' : '' }}>PA</option>
                    <option value="TA"  {{ $clas==='TA'  ? 'selected' : '' }}>TA</option>
                  </select>
                </div>
              </div>
            </div>

            {{-- Áreas (checkboxes en una tabla 3 columnas) --}}
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label>Áreas</label>
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <tbody>
                        @php
                          $sel = collect(old('areas', $areasAsignadas ?? []))->map(fn($v)=> (string)$v)->all();
                          $cols = 3; $i=0;
                        @endphp
                        <tr>
                        @foreach ($areas as $area)
                          <td>
                            <input type="checkbox"
                                   name="areas[]"
                                   id="area_{{ $area }}"
                                   value="{{ $area }}"
                                   {{ in_array((string)$area, $sel, true) ? 'checked' : '' }}>
                            <label for="area_{{ $area }}">{{ $area }}</label>
                          </td>
                          @php $i++; if($i % $cols === 0) echo '</tr><tr>'; @endphp
                        @endforeach
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <small class="text-muted">Selecciona una o más áreas (se asignarán automáticamente sus programas).</small>
                </div>
              </div>
            </div>

            {{-- Horarios disponibles --}}
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label>Horarios Disponibles</label>
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Día</th>
                        <th>Hora de Inicio</th>
                        <th>Hora de Fin</th>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="horarios_table">
                          @php
                            $dias      = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                            $oldDays   = old('day_of_week', []);
                            $oldStarts = old('start_time', []);
                            $oldEnds   = old('end_time', []);
                          @endphp

                          {{-- Caso 1: viene de validación con errores (old) --}}
                          @if(count($oldDays))
                            @foreach($oldDays as $i => $day)
                              <tr>
                                <td>
                                  <select name="day_of_week[]" class="form-control">
                                    @foreach($dias as $d)
                                      <option value="{{ $d }}" {{ $day === $d ? 'selected' : '' }}>{{ $d }}</option>
                                    @endforeach
                                  </select>
                                </td>
                                <td>
                                  @php $selStart = $oldStarts[$i] ?? null; @endphp
                                  <select name="start_time[]" class="form-control">
                                    @for($h=7; $h<=22; $h++)
                                      @php $t = sprintf('%02d:00', $h); @endphp
                                      <option value="{{ $t }}" {{ $selStart === $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endfor
                                  </select>
                                </td>
                                <td>
                                  @php $selEnd = $oldEnds[$i] ?? null; @endphp
                                  <select name="end_time[]" class="form-control">
                                    @for($h=7; $h<=22; $h++)
                                      @php $t = sprintf('%02d:00', $h); @endphp
                                      <option value="{{ $t }}" {{ $selEnd === $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endfor
                                  </select>
                                </td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                              </tr>
                            @endforeach

                          {{-- Caso 2: sin old(), pero hay horarios guardados --}}
                          @elseif(($horarios ?? collect())->count())
                            @foreach($horarios as $i => $h)
                              @php
                                $selStart = substr($h->start_time, 0, 5);
                                $selEnd   = substr($h->end_time, 0, 5);
                              @endphp
                              <tr>
                                <td>
                                  <select name="day_of_week[]" class="form-control">
                                    @foreach($dias as $d)
                                      <option value="{{ $d }}" {{ $h->day_of_week === $d ? 'selected' : '' }}>{{ $d }}</option>
                                    @endforeach
                                  </select>
                                </td>
                                <td>
                                  <select name="start_time[]" class="form-control">
                                    @for($hh=7; $hh<=22; $hh++)
                                      @php $tt = sprintf('%02d:00', $hh); @endphp
                                      <option value="{{ $tt }}" {{ $selStart === $tt ? 'selected' : '' }}>{{ $tt }}</option>
                                    @endfor
                                  </select>
                                </td>
                                <td>
                                  <select name="end_time[]" class="form-control">
                                    @for($hh=7; $hh<=22; $hh++)
                                      @php $tt = sprintf('%02d:00', $hh); @endphp
                                      <option value="{{ $tt }}" {{ $selEnd === $tt ? 'selected' : '' }}>{{ $tt }}</option>
                                    @endfor
                                  </select>
                                </td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                              </tr>
                            @endforeach

                          {{-- Caso 3: no old() y sin horarios -> una fila vacía --}}
                          @else
                            <tr>
                              <td>
                                <select name="day_of_week[]" class="form-control">
                                  @foreach($dias as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                                </select>
                              </td>
                              <td>
                                <select name="start_time[]" class="form-control">
                                  @for($h=7; $h<=22; $h++)
                                    @php $t = sprintf('%02d:00', $h); @endphp
                                    <option value="{{ $t }}">{{ $t }}</option>
                                  @endfor
                                </select>
                              </td>
                              <td>
                                <select name="end_time[]" class="form-control">
                                  @for($h=7; $h<=22; $h++)
                                    @php $t = sprintf('%02d:00', $h); @endphp
                                    <option value="{{ $t }}">{{ $t }}</option>
                                  @endfor
                                </select>
                              </td>
                              <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                            </tr>
                          @endif
                        </tbody>

                  </table>
                  <button type="button" id="addHorario" class="btn btn-success btn-sm">Agregar Horario</button>
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-success">Guardar cambios</button>
                  <a href="{{ route('profesores.index') }}" class="btn btn-secondary">Cancelar</a>
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
<script>
(function(){
  function timeSelect(name, selected){
    let html = `<select name="${name}" class="form-control">`;
    for(let h=7; h<=22; h++){
      const t = (h<10? '0'+h:h)+':00';
      html += `<option value="${t}" ${selected===t?'selected':''}>${t}</option>`;
    }
    html += `</select>`;
    return html;
  }

  const dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  const tbody = document.getElementById('horarios_table');
  document.getElementById('addHorario').addEventListener('click', function(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="day_of_week[]" class="form-control">
          ${dias.map(d=>`<option value="${d}">${d}</option>`).join('')}
        </select>
      </td>
      <td>${timeSelect('start_time[]')}</td>
      <td>${timeSelect('end_time[]')}</td>
      <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
    `;
    tbody.appendChild(tr);
  });

  tbody.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-row')){
      e.target.closest('tr').remove();
    }
  });
})();
</script>
@endsection
