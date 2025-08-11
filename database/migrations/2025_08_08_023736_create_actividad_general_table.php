<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('actividad_general')) {
            Schema::create('actividad_general', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('usuario_id');
                $t->string('nombre_usuario', 255);
                $t->string('accion', 100);
                $t->string('tabla', 100);
                $t->unsignedBigInteger('registro_id')->nullable();
                $t->text('descripcion')->nullable();
                $t->timestamp('fecha')->useCurrent();
            });
        } else {
            Schema::table('actividad_general', function (Blueprint $t) {
                if (!Schema::hasColumn('actividad_general','id'))            $t->bigIncrements('id');
                if (!Schema::hasColumn('actividad_general','usuario_id'))     $t->unsignedInteger('usuario_id');
                if (!Schema::hasColumn('actividad_general','nombre_usuario')) $t->string('nombre_usuario',255);
                if (!Schema::hasColumn('actividad_general','accion'))         $t->string('accion',100);
                if (!Schema::hasColumn('actividad_general','tabla'))          $t->string('tabla',100);
                if (!Schema::hasColumn('actividad_general','registro_id'))    $t->unsignedBigInteger('registro_id')->nullable();
                if (!Schema::hasColumn('actividad_general','descripcion'))    $t->text('descripcion')->nullable();
                if (!Schema::hasColumn('actividad_general','fecha'))          $t->timestamp('fecha')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_general');
    }
};
