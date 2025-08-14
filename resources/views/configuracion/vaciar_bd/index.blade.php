{{-- resources/views/configuracion/vaciar_bd/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Gestión de Vaciado de Tablas')

@section('content_header')
  <h1 class="text-center w-100">Gestión de Vaciado de Tablas</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Tablas registradas</h3>
        </div>

        <div class="card-body">
          <table id="example1" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th class="text-center">Nombre de Tabla</th>
                <th class="text-center">Total de Registros</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {{-- 1) Grupos --}}
              <tr>
                <td class="text-center">1</td>
                <td class="text-center">Grupos</td>
                <td class="text-center">{{ $resumen['grupos']['existe'] ? ($resumen['grupos']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.grupos.truncate') }}"
                            method="POST" id="formVaciarGrupos">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarGrupos', 'Grupos', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

              {{-- 2) Materias --}}
              <tr>
                <td class="text-center">2</td>
                <td class="text-center">Materias</td>
                <td class="text-center">{{ $resumen['materias']['existe'] ? ($resumen['materias']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.materias.truncate') }}"
                            method="POST" id="formVaciarMaterias">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarMaterias', 'Materias', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

              {{-- 3) Profesores --}}
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">Profesores</td>
                <td class="text-center">{{ $resumen['profesores']['existe'] ? ($resumen['profesores']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.profesores.truncate') }}"
                            method="POST" id="formVaciarProfesores">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarProfesores', 'Profesores', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

              {{-- 4) Asignaciones (materia <-> profesor) --}}
              <tr>
                <td class="text-center">4</td>
                <td class="text-center">Asignaciones de Materias a Profesores</td>
                <td class="text-center">{{ $resumen['asignaciones']['existe'] ? ($resumen['asignaciones']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.asignaciones.truncate') }}"
                            method="POST" id="formVaciarAsignaciones">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarAsignaciones', 'Asignaciones de Materias a Profesores', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

              {{-- 5) Horario Escolar (auto) --}}
              <tr>
                <td class="text-center">5</td>
                <td class="text-center">Horario Escolar</td>
                <td class="text-center">{{ $resumen['horario_escolar']['existe'] ? ($resumen['horario_escolar']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.horario-escolar.truncate') }}"
                            method="POST" id="formVaciarHorarioEscolar">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarHorarioEscolar', 'Horario Escolar', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

              {{-- 6) Horario Escolar (manual) --}}
              <tr>
                <td class="text-center">6</td>
                <td class="text-center">Horario Escolar (Manual)</td>
                <td class="text-center">{{ $resumen['horario_escolar_manual']['existe'] ? ($resumen['horario_escolar_manual']['count'] ?? 0) : '—' }}</td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    @can('vaciar bd')
                      <form action="{{ route('configuracion.vaciar-bd.horario-escolar-manual.truncate') }}"
                            method="POST" id="formVaciarHorarioEscolarManual">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="confirmarVaciado('formVaciarHorarioEscolarManual', 'Horario Escolar (Manual)', this)">
                          <i class="bi bi-trash"></i> Vaciar
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>
              </tr>

            </tbody>
          </table>
        </div> {{-- card-body --}}
      </div> {{-- card --}}
    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* Confirmación de vaciado con defensa contra doble clic */
function confirmarVaciado(formId, nombreTabla, btn){
  const form = document.getElementById(formId);
  if(!form){ console.error('No existe', formId); return; }

  if(btn){ btn.disabled = true; }

  if (typeof Swal === 'undefined') {
    if (confirm('¿Desea eliminar todos los registros de ' + nombreTabla + '?')) form.submit();
    else if(btn){ btn.disabled = false; }
    return;
  }

  Swal.fire({
    title: 'Vaciar ' + nombreTabla,
    text: '¿Desea eliminar todos los registros de ' + nombreTabla + '? Esta acción no se puede deshacer.',
    icon: 'warning',
    showDenyButton: true,
    confirmButtonText: 'Eliminar',
    confirmButtonColor: '#E43636',
    denyButtonColor: '#007bff',
    denyButtonText: 'Cancelar',
  }).then((r)=>{
    if(r.isConfirmed){
      form.submit();
    }else if(btn){
      btn.disabled = false;
    }
  });
}
</script>

{{-- Alertas post-redirect (flash) --}}
@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: @json(session('success')),
  showConfirmButton: false,
  timer: 8000,
  timerProgressBar: true,
  position: 'center'
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
  icon: 'error',
  title: 'Ups',
  text: @json(session('error')),
  confirmButtonColor: '#E43636',
  position: 'center'
});
</script>
@endif

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

{{-- DataTables --}}
<script>
$(function () {
  const dt = $("#example1").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Tablas",
      infoEmpty: "Mostrando 0 a 0 de 0 Tablas",
      infoFiltered: "(Filtrado de _MAX_ total Tablas)",
      lengthMenu: "Mostrar _MENU_ Tablas",
      search: "Buscador:",
      zeroRecords: "Sin resultados encontrados",
      paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
    },
    responsive: true, lengthChange: true, autoWidth: false,
    buttons: [
      { extend:'collection', text:'Opciones', orientation:'landscape', buttons:['copy','pdf','csv','excel','print'] },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });
  dt.buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
