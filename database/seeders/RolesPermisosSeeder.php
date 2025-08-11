<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Permisos base del sistema de carga horaria =====
        $permisos = [
            // Horarios
            'ver horarios',
            'crear horarios',
            'editar horarios',
            'borrar horarios',
            'intercambiar horarios',
            'ver horarios grupos',
            'ver horarios profesores',

            // Horarios Laboratorio
            'bloquear horario',
            'ver horario laboratorio',
            'crear horario laboratorio',
            'editar horario laboratorio',
            'borrar horario laboratorio',


            //Profesores
            'ver profesores',
            'crear profesores',
            'editar profesores',
            'borrar profesores',
            'asignar materias',

            // Catálogos
            'ver grupos',
            'ver materias',
            'ver salones',
            'ver turnos',
            'ver periodos',

            // Gestión (opcional según tu flujo)
            'asignar profesor',
            'asignar salon manual',
            'editar limites consecutivos',
            'editar horas semanales',

            // Admin del sistema
            'administrar roles y permisos',

            // ===== Configuración =====

            'ver configuraciones',

            // Usuarios
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',

            // Roles
            'ver roles',
            'crear roles',
            'editar roles',
            'eliminar roles',

            // Vaciar base de datos
            'ver vaciar bd',
            'vaciar bd',

            // Eliminar materias
            'ver eliminar materias',
            'eliminar materias',

            // Activar usuarios
            'ver activar usuarios',
            'activar usuarios',

            // Estadísticas
            'ver estadisticas',
            'crear estadisticas',
            'editar estadisticas',
            'eliminar estadisticas',

            // Calendario escolar
            'ver calendario escolar',
            'crear calendario escolar',
            'editar calendario escolar',
            'eliminar calendario escolar',

            // Mapa escolar
            'ver mapa escolar',
            'crear mapa escolar',
            'editar mapa escolar',
            'eliminar mapa escolar',

            // Horarios pasados
            'ver horarios pasados',
            'crear horarios pasados',
            'editar horarios pasados',
            'eliminar horarios pasados',

            // Registro de actividad
            'ver registro actividad',
            'crear registro actividad',
            'editar registro actividad',
            'eliminar registro actividad',

             // Profesores
            'ver profesores',
            'crear profesores',
            'editar profesores',
            'eliminar profesores',

            // Materias
            'ver materias',
            'crear materias',
            'editar materias',
            'eliminar materias',

            // Programas
            'ver programas',
            'crear programas',
            'editar programas',
            'eliminar programas',

            // Grupos
            'ver grupos',
            'crear grupos',
            'editar grupos',
            'eliminar grupos',

            // Salones
            'ver salones',
            'crear salones',
            'editar salones',
            'eliminar salones',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // ===== Roles y sus permisos =====
        $rolesConPermisos = [
            'Administrador' => $permisos, // todo
            'Subdirector' => [
                'ver horarios','crear horarios','editar horarios','bloquear horario',
                'autoasignar salones','revertir autoasignacion','exportar horarios pdf',
                'ver grupos','ver materias','ver profesores','ver salones','ver turnos','ver periodos',
                'asignar profesor','asignar salon manual','editar limites consecutivos','editar horas semanales',
            ],
            'Profesor' => [
                'ver horarios','ver grupos','ver materias','ver periodos',
            ],
            'Observador' => [
                'ver horarios','ver grupos','ver materias',
            ],
        ];

        foreach ($rolesConPermisos as $rol => $perms) {
            $role = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // ===== Mapear rol_id existente → rol Spatie =====
        // Ajusta los valores a los que tengas en tu BD
        $map = [
            1 => 'Administrador',
            2 => 'Subdirector',
            3 => 'Profesor',
            4 => 'Observador',
        ];

        User::query()->each(function (User $u) use ($map) {
            if (isset($map[$u->rol_id])) {
                $u->syncRoles([$map[$u->rol_id]]);
            }
        });
    }
}
