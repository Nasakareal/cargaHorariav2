<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;

class PerfilApiController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', Password::min(8)],
        ]);

        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'foto' => ['required','image','max:4096'],
        ]);

        $user = $request->user();

        $path = $request->file('foto')->store('perfil', 'public');
        $publicUrl = asset('storage/'.$path);
        $user->foto_perfil = $publicUrl;
        $user->save();

        return response()->json(['ok' => true, 'foto_perfil' => $publicUrl]);
    }
}
