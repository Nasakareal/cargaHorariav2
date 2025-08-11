<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ===== ROLES: agrega columnas que Spatie espera =====
        if (Schema::hasTable('roles')) {
            // id (espejo de id_rol) — sin cambiar PK
            if (!Schema::hasColumn('roles','id')) {
                Schema::table('roles', function (Blueprint $t) {
                    $t->unsignedBigInteger('id')->nullable()->after('id_rol');
                });
                DB::statement('UPDATE roles SET id = id_rol');
                DB::statement('ALTER TABLE roles MODIFY id BIGINT UNSIGNED NOT NULL');
                try { DB::statement('CREATE INDEX roles_id_index ON roles (id)'); } catch (\Throwable $e) {}
            }

            // name (desde nombre_rol o fallback)
            if (!Schema::hasColumn('roles','name')) {
                Schema::table('roles', function (Blueprint $t) {
                    $t->string('name',255)->nullable();
                });
                if (Schema::hasColumn('roles','nombre_rol')) {
                    DB::statement("UPDATE roles SET name = nombre_rol WHERE name IS NULL OR name = ''");
                }
                DB::statement("UPDATE roles SET name = CONCAT('role_', COALESCE(id_rol, id)) WHERE name IS NULL OR name = ''");
                DB::statement("ALTER TABLE roles MODIFY name VARCHAR(255) NOT NULL");
            }

            // guard_name (Spatie lo usa; default 'web')
            if (!Schema::hasColumn('roles','guard_name')) {
                Schema::table('roles', function (Blueprint $t) {
                    $t->string('guard_name',191)->nullable();
                });
                DB::statement("UPDATE roles SET guard_name = 'web' WHERE guard_name IS NULL OR guard_name = ''");
                DB::statement("ALTER TABLE roles MODIFY guard_name VARCHAR(191) NOT NULL DEFAULT 'web'");
            }

            // NO creamos índice único (name,guard_name) para evitar bloquearte si hay duplicados.
        }
    }

    public function down(): void
    {
        // No hacemos rollback para no afectar tu esquema legacy
    }
};
