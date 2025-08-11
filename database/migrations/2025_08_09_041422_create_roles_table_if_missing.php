<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('roles_legacy') && DB::table('roles')->count() === 0) {
            $col = null;
            $cols = Schema::getColumnListing('roles_legacy');
            foreach (['name','nombre','rol'] as $c) {
                if (in_array($c, $cols, true)) { $col = $c; break; }
            }
            if ($col) {
                $rows = DB::table('roles_legacy')->select($col)->distinct()->get();
                foreach ($rows as $r) {
                    $name = $r->$col;
                    if ($name) {
                        DB::table('roles')->updateOrInsert(
                            ['name'=>$name,'guard_name'=>'web'],
                            ['created_at'=>now(),'updated_at'=>now()]
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {

    }
};
