@extends('adminlte::page')

@section('title', 'Historial de Horarios')

@section('content_header')
  <h1 class="text-center w-100">Historial de Horarios</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Horarios archivados por fecha</h3>
          @can('crear horarios pasados')
            <a href="{{ route('configuracion.horarios-pasados.create') }}"
               class="btn btn-danger"
               title="Archivar horarios actuales (mover y limpiar)">
              <i class="bi bi-archive"></i> Archivar Horarios Actuales
            </a>
          @endcan
        </div>

        <div class="card-body">
          <table id="tabla" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th class="text-center">Cuatrimestre</th>
                <th class="text-center">Fecha</th>
                <th class="text-center">Total</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($paquetes as $i => $p)
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td class="text-center">{{ $p->quarter_name_en ?? '—' }}</td>
                  <td class="text-center">{{ $p->fecha }}</td>
                  <td class="text-center">{{ $p->total }}</td>
                  <td class="text-center">
                    <div class="btn-group">
                      <a href="{{ route('configuracion.horarios-pasados.show', $p->fecha) }}"
                         class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>
                      @can('editar horarios pasados')
                        <a href="{{ route('configuracion.horarios-pasados.edit', $p->fecha) }}"
                           class="btn btn-success btn-sm" title="Renombrar">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      @endcan
                      @can('eliminar horarios pasados')
                        <form action="{{ route('configuracion.horarios-pasados.destroy', $p->fecha) }}"
                              method="POST" onsubmit="return confirmarEliminar(this)">
                          @csrf @method('DELETE')
                          <button class="btn btn-danger btn-sm" type="submit" title="Eliminar paquete">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      @endcan
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center">Sin paquetes archivados todavía.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminar(form){
  if (typeof Swal === 'undefined') return confirm('¿Eliminar el paquete?');
  Swal.fire({
    title: 'Eliminar paquete',
    text: 'Esto borrará todos los registros de esa fecha.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#E43636',
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
  }).then(r => { if(r.isConfirmed) form.submit(); });
  return false;
}

@if (session('success'))
Swal.fire({icon:'success', title:@json(session('success')), timer:4500, showConfirmButton:false, position:'center'});
@endif
@if (session('error'))
Swal.fire({icon:'error', title:'Ups', text:@json(session('error')), position:'center'});
@endif

// DataTables simple
document.addEventListener('DOMContentLoaded', () => {
  if (window.$ && $.fn.DataTable) {
    $('#tabla').DataTable({
      pageLength: 10,
      language: {
        emptyTable: "No hay información",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(Filtrado de _MAX_ registros)",
        lengthMenu: "Mostrar _MENU_ registros",
        search: "Buscador:",
        zeroRecords: "Sin resultados encontrados",
        paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
      },
      responsive: true, lengthChange: true, autoWidth: false
    });
  }
});
</script>
@endsection
