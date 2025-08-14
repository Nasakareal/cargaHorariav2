@extends('adminlte::page')

@section('title', 'Mi Perfil')

@section('content_header')
  <h1>Mi Perfil</h1>
@endsection

@section('content')
  @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="row">
    {{-- Columna foto / avatar --}}
    <div class="col-lg-4">
      <div class="card card-outline card-primary">
        <div class="card-body text-center">
          {{-- Usa $user para evitar caché del Auth en este render --}}
          <img src="{{ $user->adminlte_image() }}" class="img-fluid rounded-circle mb-3" style="max-width:160px;">
          <h5 class="mb-1">{{ $user->name }}</h5>
          <small class="text-muted">{{ $user->adminlte_desc() }}</small>
          <hr>

          {{-- Subir foto --}}
          <form action="{{ route('perfil.foto') }}" method="POST" enctype="multipart/form-data" class="mb-3">
            @csrf
            <input type="hidden" name="accion" value="subir">
            <div class="form-group text-left">
              <label>Subir nueva foto (jpg/png/webp, máx. 2MB)</label>
              <input type="file" name="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/*" required>
              @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-primary btn-block">Subir</button>
          </form>

          {{-- Elegir avatar --}}
          <form action="{{ route('perfil.foto') }}" method="POST" class="mb-3">
            @csrf
            <input type="hidden" name="accion" value="avatar">
            <label class="d-block text-left mb-2">Elegir avatar</label>
            <div class="d-flex flex-wrap" style="gap:8px;max-height:180px;overflow:auto;">
              @foreach($avatars as $file)
                <label class="border p-1" style="cursor:pointer;border-radius:8px;">
                  <input type="radio" name="selected_avatar" value="{{ $file }}" class="mr-1"
                         {{ $user->foto_perfil === $file ? 'checked' : '' }}>
                  <img src="{{ asset('img/avatar/'.$file) }}" width="40" height="40" alt="{{ $file }}">
                </label>
              @endforeach
            </div>
            <button class="btn btn-outline-primary btn-block mt-2">Usar avatar</button>
          </form>

          {{-- Quitar foto (volver a avatar por defecto) --}}
          <form action="{{ route('perfil.foto') }}" method="POST">
            @csrf
            <input type="hidden" name="accion" value="quitar">
            <button class="btn btn-outline-danger btn-block">Quitar foto actual</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Columna contraseña --}}
    <div class="col-lg-8">
      <div class="card card-outline card-success">
        <div class="card-header"><h3 class="card-title">Cambiar contraseña</h3></div>
        <div class="card-body">
          <form method="POST" action="{{ route('perfil.password') }}">
            @csrf
            <div class="form-group">
              <label>Contraseña actual</label>
              <input type="password" name="password_actual" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Nueva contraseña</label>
              <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
              <label>Confirmar contraseña</label>
              <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>
            <button class="btn btn-success">Guardar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
