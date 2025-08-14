<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PerfilController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $avatars = $this->avatarList(); // ['avatar-1.png', ..., 'avatar-25.png']
        return view('perfil.index', compact('user','avatars'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_actual' => ['required','string','min:6'],
            'password'        => ['required','string','min:8','confirmed'],
        ], [], [
            'password_actual' => 'contraseña actual',
            'password'        => 'nueva contraseña',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return back()->with('error', 'La contraseña actual no coincide.')->withInput();
        }

        $user->password = $request->password; // setPasswordAttribute hace el hash
        $user->fyh_actualizacion = now();
        $user->save();

        // (opcional) registrar actividad
        if (class_exists(\App\Models\ActividadGeneral::class)) {
            \App\Models\ActividadGeneral::registrar('ACTUALIZAR', 'usuarios', $user->id_usuario, 'Actualizó su contraseña');
        }

        // refrescar sesión
        auth()->login($user->fresh());

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'accion'           => ['required','in:subir,avatar,quitar'],
            'foto'             => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'selected_avatar'  => ['nullable','string', Rule::in($this->avatarList())],
        ], [], [
            'foto'            => 'foto de perfil',
            'selected_avatar' => 'avatar',
        ]);

        // Quitar foto -> deja null (usará fallback determinístico)
        if ($request->accion === 'quitar') {
            if ($user->foto_perfil && !Str::startsWith($user->foto_perfil, 'avatar-')) {
                @File::delete(public_path('uploads/perfiles/'.$user->foto_perfil));
            }
            $user->foto_perfil = null;
            $user->fyh_actualizacion = now();
            $user->save();
            auth()->login($user->fresh());
            return back()->with('success','Foto eliminada. Se usará el avatar por defecto.');
        }

        // Usar uno de los avatares predefinidos
        if ($request->accion === 'avatar' && $request->selected_avatar) {
            $user->foto_perfil = $request->selected_avatar; // p.ej. "avatar-10.png"
            $user->fyh_actualizacion = now();
            $user->save();
            auth()->login($user->fresh());
            return back()->with('success', 'Avatar actualizado correctamente.');
        }

        // Subir archivo
        if ($request->accion === 'subir' && $request->hasFile('foto')) {
            $file = $request->file('foto');

            $dir = public_path('uploads/perfiles');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // borrar anterior si era archivo subido (no avatar)
            if ($user->foto_perfil && !Str::startsWith($user->foto_perfil, 'avatar-')) {
                @File::delete(public_path('uploads/perfiles/'.$user->foto_perfil));
            }

            $ext  = strtolower($file->getClientOriginalExtension());
            $name = 'u'.$user->id_usuario.'_'.time().'.'.$ext;
            $file->move($dir, $name);

            $user->foto_perfil = $name;
            $user->fyh_actualizacion = now();
            $user->save();
            auth()->login($user->fresh());

            return back()->with('success', 'Foto subida correctamente.');
        }

        return back()->with('error','No se pudo actualizar la foto.');
    }

    private function avatarList(): array
    {
        $files = glob(public_path('img/avatar/avatar-*.png')) ?: [];
        sort($files, SORT_NATURAL);
        return array_map(fn($p) => basename($p), $files);
    }
}
