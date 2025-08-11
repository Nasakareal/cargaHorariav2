@extends('adminlte::auth.auth-page', ['auth_type' => 'login'])

@section('title', 'Login | Sistema de Carga Horaria')

@section('adminlte_css')
    <link rel="icon" href="{{ asset('UTM.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('UTM.png') }}">
@endsection


@section('auth_header')
    <center>
        <img src="{{ asset('logo_2025.png') }}" width="340px" alt="UTM Logo"><br><br>
    </center>
    <h3><b>Sistema de Carga Horaria</b></h3>
    <p class="login-box-msg">Inicio de sesión</p>
    <hr>
@endsection

@section('auth_body')
    <form action="{{ route('login') }}" method="post">
        @csrf
        <div class="input-group mb-3">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" placeholder="Email" required autofocus>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Password" required>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <hr>
        <div class="input-group mb-3">
            <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
        </div>
    </form>
@endsection

@section('auth_footer')
    @if(session('error'))
        <script>
            Swal.fire({
                position: "top-center",
                icon: "error",
                title: "{{ session('error') }}",
                showConfirmButton: false,
                timer: 4000
            });
        </script>
    @endif
@endsection
