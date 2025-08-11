<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActividadGeneralTable extends Migration
{
    public function up()
    {
        Schema::create('actividad_general', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->string('nombre_usuario', 255);
            $table->string('accion', 100); // Crear, Editar, Eliminar, etc.
            $table->string('tabla', 100);
            $table->unsignedBigInteger('registro_id')->nullable(); // ID del registro afectado
            $table->text('descripcion')->nullable(); // Descripción opcional
            $table->timestamp('fecha')->default(DB::raw('CURRENT_TIMESTAMP'));

            // Clave foránea opcional (puedes quitarla si no quieres relación directa)
            $table->foreign('usuario_id')->references('id_usuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('actividad_general');
    }
}
