@extends('adminlte::page')

@section('title', 'Mi Perfil')

@section('content_header')
  <h1 class="text-center w-100">Mi Perfil</h1>
@endsection

@section('content')
<div class="container-xl">

  @if(session('error'))
    <div class="alert alert-danger">{!! session('error') !!}</div>
  @endif
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-4">
      <div class="card card-outline card-info">
        <div class="card-body text-center">
          <img src="{{ Auth::user()->adminlte_image() }}" alt="Foto perfil" class="img-circle elevation-2 mb-3" style="width:120px;height:120px;object-fit:cover;">
          <h5 class="mb-1">{{ $user->nombres }}</h5>
          <div class="text-muted">{{ $user->email }}</div>
          <div class="text-muted">{{ $user->rol_nombre ?? 'Usuario' }}</div>
          <div class="text-muted">{{ $user->area ?? '' }}</div>
        </div>
      </div>

      <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">Cambiar foto</h3></div>
        <div class="card-body">
          {{-- Subir archivo --}}
          <form action="{{ route('perfil.foto') }}" method="POST" enctype="multipart/form-data" class="mb-3">
            @csrf
            <input type="hidden" name="accion" value="subir">
            <div class="form-group">
              <label for="foto">Subir nueva foto (jpg/png/webp, máx 2MB)</label>
              <input type="file" name="foto" id="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-upload"></i> Subir</button>
          </form>

          {{-- Elegir avatar --}}
          <form action="{{ route('perfil.foto') }}" method="POST" class="mb-3">
            @csrf
            <input type="hidden" name="accion" value="avatar">
            <label>Elegir avatar:</label>
            <div class="d-flex flex-wrap" style="gap:8px">
              @foreach($avatars as $a)
                <label class="mb-0" style="cursor:pointer">
                  <input type="radio" name="selected_avatar" value="{{ $a }}" class="d-none" required>
                  <img src="{{ asset('img/avatar/'.$a) }}" alt="{{ $a }}" class="img-thumbnail" style="width:56px;height:56px;object-fit:cover;">
                </label>
              @endforeach
            </div>
            <button class="btn btn-outline-primary btn-sm mt-2" type="submit"><i class="bi bi-person-square"></i> Usar avatar</button>
          </form>

          {{-- Quitar foto (usar avatar por defecto) --}}
          <form action="{{ route('perfil.foto') }}" method="POST">
            @csrf
            <input type="hidden" name="accion" value="quitar">
            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Quitar foto</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card card-outline card-success">
        <div class="card-header"><h3 class="card-title">Cambiar contraseña</h3></div>
        <form action="{{ route('perfil.password') }}" method="POST" autocomplete="off">
          @csrf
          <div class="card-body">
            <div class="form-group">
              <label for="password_actual">Contraseña actual</label>
              <input type="password" name="password_actual" id="password_actual" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="password">Nueva contraseña</label>
              <input type="password" name="password" id="password" class="form-control" required minlength="8">
              <small class="form-text text-muted">Mínimo 8 caracteres.</small>
            </div>
            <div class="form-group">
              <label for="password_confirmation">Confirmar nueva contraseña</label>
              <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="8">
            </div>
          </div>
          <div class="card-footer">
            <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
@endsection

@section('js')
<script>
// marcar el avatar clicado
document.querySelectorAll('form [name="selected_avatar"] + img').forEach(img => {
  img.addEventListener('click', () => {
    const radio = img.previousElementSibling;
    document.querySelectorAll('form [name="selected_avatar"]').forEach(r => r.checked = false);
    radio.checked = true;
  });
});
</script>
@endsection
