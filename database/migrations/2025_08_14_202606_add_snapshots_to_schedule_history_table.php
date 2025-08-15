<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schedule_history', function (Blueprint $table) {
            $table->string('teacher_name', 150)->nullable()->after('teacher_id');
            $table->string('group_name', 50)->nullable()->after('group_id');
            $table->string('subject_name', 150)->nullable()->after('subject_id');
            $table->string('program', 20)->nullable()->after('group_name');
            $table->string('turno', 20)->nullable()->after('program');
            $table->string('classroom_name', 50)->nullable()->after('classroom_id');
            $table->string('lab_name', 50)->nullable()->after('lab_id');
            $table->index(['teacher_id']);
            $table->index(['group_id']);
            $table->index(['subject_id']);
            $table->index(['schedule_day']);
            $table->index(['teacher_name']);
            $table->index(['group_name']);
            $table->index(['subject_name']);
        });

        DB::statement("ALTER TABLE `schedule_history` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci");
    }

    public function down(): void
    {
        Schema::table('schedule_history', function (Blueprint $table) {
            $table->dropIndex(['teacher_id']);
            $table->dropIndex(['group_id']);
            $table->dropIndex(['subject_id']);
            $table->dropIndex(['schedule_day']);
            $table->dropIndex(['teacher_name']);
            $table->dropIndex(['group_name']);
            $table->dropIndex(['subject_name']);

            $table->dropColumn([
                'teacher_name',
                'group_name',
                'subject_name',
                'program',
                'turno',
                'classroom_name',
                'lab_name',
            ]);
        });
    }
};
