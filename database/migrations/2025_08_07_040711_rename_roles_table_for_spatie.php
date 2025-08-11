<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::rename('roles', 'roles_legacy');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles_legacy') && Schema::hasTable('roles')) {
            Schema::dropIfExists('roles');
            Schema::rename('roles_legacy', 'roles');
        }
    }
};
