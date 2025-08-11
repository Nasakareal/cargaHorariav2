@extends('adminlte::page')

@section('title', 'Horarios de Profesores')

@section('content_header')
  <h1 class="text-center w-100">Listado de Horarios de Profesores</h1>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Profesores</h3>
        </div>

        <div class="card-body">

          {{-- Filtro por nombre --}}
          <form method="GET" action="{{ route('horarios.profesores.index') }}" class="mb-3">
            @php $qOld = $q ?? request('q', ''); @endphp
            <div class="row g-2">
              <div class="col-md-9">
                <input type="text" name="q" value="{{ $qOld }}" class="form-control" placeholder="Buscar profesor por nombre…">
              </div>
              <div class="col-md-3 text-end">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="{{ route('horarios.profesores.index') }}" class="btn btn-outline-secondary">
                  Limpiar
                </a>
              </div>
            </div>
          </form>

          <table id="tablaProfesores" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center" style="width:60px;">#</th>
                <th class="text-center">Docente</th>
                <th class="text-center" style="width:140px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($profesores as $i => $p)
                @php
                  $id      = $p->teacher_id ?? $p->id ?? $p->id_profesor ?? null;
                  $docente = $p->docente ?? $p->nombre_completo ?? $p->name ?? $p->nombre ?? '—';
                @endphp
                <tr>
                  <td class="text-center">{{ is_int($i) ? $i + 1 : $loop->iteration }}</td>
                  <td class="text-center">{{ $docente }}</td>
                  <td class="text-center">
                    <a href="{{ route('horarios.profesores.show', $id) }}" class="btn btn-info btn-sm" title="Ver horario">
                      <i class="bi bi-eye"></i> Ver
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          {{-- Paginación del servidor (si vienes con paginate()) --}}
          @if ($profesores instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-3">
              {{ $profesores->withQueryString()->links() }}
            </div>
          @endif

        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(function () {
  const dt = $("#tablaProfesores").DataTable({
    pageLength: 10,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json',
      info: "Mostrando _START_ a _END_ de _TOTAL_ Profesores"
    },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Bfrtip',
    buttons: [
      { extend:'collection', text:'Opciones', buttons:[
          { extend:'copyHtml5',  text:'Copiar'  },
          { extend:'csvHtml5',   text:'CSV'     },
          { extend:'excelHtml5', text:'Excel',  filename:'Profesores' },
          { extend:'pdfHtml5',   text:'PDF',    filename:'Profesores', orientation:'portrait', pageSize:'LETTER' },
          { extend:'print',      text:'Imprimir' }
      ]},
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });
  dt.buttons().container().appendTo('#tablaProfesores_wrapper .col-md-6:eq(0)');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if (session('success'))
<script>
Swal.fire({ icon:'success', title:@json(session('success')), showConfirmButton:false, timer:6500, timerProgressBar:true, position:'center' });
</script>
@endif
@if (session('error'))
<script>
Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), confirmButtonColor:'#E43636', position:'center' });
</script>
@endif
@if ($errors->any())
<script>
Swal.fire({ icon:'warning', title:'Revisa los datos', html:`{!! implode('<br>', $errors->all()) !!}`, position:'center' });
</script>
@endif
@endsection
