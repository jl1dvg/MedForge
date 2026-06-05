<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgendaV3Seeder extends Seeder
{
    public function run(): void
    {
        // Limpiar médicos falsos (delete en vez de truncate por FK con agenda_horarios)
        DB::table('agenda_horarios')->delete();
        DB::table('agenda_medicos')->delete();

        DB::table('agenda_sedes')->upsert([
            ['id' => 'ceibos',    'label' => 'Ceibos',     'abrev' => 'CB', 'apertura' => '08:00:00', 'cierre' => '18:00:00', 'activo' => 1],
            ['id' => 'villaclub', 'label' => 'Villa Club', 'abrev' => 'VC', 'apertura' => '08:00:00', 'cierre' => '17:00:00', 'activo' => 1],
        ], ['id'], ['label', 'abrev', 'apertura', 'cierre', 'activo']);

        // agenda_medicos se puebla automáticamente desde procedimiento_proyectado
        // vía syncMedicosFromPP() en el primer hit a GET /api/agenda/v3/config

        DB::table('agenda_salas')->upsert([
            ['id' => 's_cons1', 'sede_id' => 'ceibos',    'label' => 'Consultorio 1',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_cons2', 'sede_id' => 'ceibos',    'label' => 'Consultorio 2',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_cons3', 'sede_id' => 'ceibos',    'label' => 'Consultorio 3',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_opto',  'sede_id' => 'ceibos',    'label' => 'Box optometría',     'tipo' => 'box',          'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_qx1',   'sede_id' => 'ceibos',    'label' => 'Quirófano 1',        'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
            ['id' => 's_qx2',   'sede_id' => 'ceibos',    'label' => 'Quirófano 2',        'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
            ['id' => 's_proc',  'sede_id' => 'ceibos',    'label' => 'Sala procedimientos','tipo' => 'procedimiento','area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
            ['id' => 's_laser', 'sede_id' => 'ceibos',    'label' => 'Sala láser',         'tipo' => 'laser',        'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
            ['id' => 's_img1',  'sede_id' => 'ceibos',    'label' => 'Imágenes A (OCT)',   'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
            ['id' => 's_img2',  'sede_id' => 'ceibos',    'label' => 'Imágenes B (campo)', 'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
            ['id' => 's_com',   'sede_id' => 'ceibos',    'label' => 'Asesoría comercial', 'tipo' => 'comercial',    'area' => 'comercial',  'cap' => 1, 'activo' => 1],
            ['id' => 's_vcA',   'sede_id' => 'villaclub', 'label' => 'Consultorio A',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_vcB',   'sede_id' => 'villaclub', 'label' => 'Consultorio B',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
            ['id' => 's_vcqx',  'sede_id' => 'villaclub', 'label' => 'Quirófano VC',       'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
            ['id' => 's_vcimg', 'sede_id' => 'villaclub', 'label' => 'Imágenes VC',        'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
        ], ['id'], ['sede_id', 'label', 'tipo', 'area', 'cap', 'activo']);

        DB::table('agenda_tipos_cita')->upsert([
            ['id' => 't_cons',    'label' => 'Consulta oftalmológica',    'area' => 'consulta',   'dur_minutos' => 20, 'requiere_tipo_sala' => '["consultorio"]',              'activo' => 1],
            ['id' => 't_primera', 'label' => 'Consulta primera vez',      'area' => 'consulta',   'dur_minutos' => 30, 'requiere_tipo_sala' => '["consultorio"]',              'activo' => 1],
            ['id' => 't_postop',  'label' => 'Control post-operatorio',   'area' => 'consulta',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["consultorio"]',              'activo' => 1],
            ['id' => 't_opto',    'label' => 'Optometría / refracción',   'area' => 'consulta',   'dur_minutos' => 30, 'requiere_tipo_sala' => '["box","consultorio"]',        'activo' => 1],
            ['id' => 't_faco',    'label' => 'Facoemulsificación + LIO',  'area' => 'quirurgico', 'dur_minutos' => 45, 'requiere_tipo_sala' => '["quirofano"]',                'activo' => 1],
            ['id' => 't_vpp',     'label' => 'Vitrectomía pars plana',    'area' => 'quirurgico', 'dur_minutos' => 90, 'requiere_tipo_sala' => '["quirofano"]',                'activo' => 1],
            ['id' => 't_antivegf','label' => 'Inyección intravítrea',     'area' => 'quirurgico', 'dur_minutos' => 20, 'requiere_tipo_sala' => '["procedimiento"]',            'activo' => 1],
            ['id' => 't_yag',     'label' => 'Capsulotomía láser YAG',    'area' => 'quirurgico', 'dur_minutos' => 15, 'requiere_tipo_sala' => '["laser"]',                    'activo' => 1],
            ['id' => 't_oct',     'label' => 'OCT macular',               'area' => 'imagenes',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["imagen"]',                   'activo' => 1],
            ['id' => 't_campo',   'label' => 'Campimetría 24-2',          'area' => 'imagenes',   'dur_minutos' => 20, 'requiere_tipo_sala' => '["imagen"]',                   'activo' => 1],
            ['id' => 't_topo',    'label' => 'Topografía corneal',        'area' => 'imagenes',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["imagen"]',                   'activo' => 1],
            ['id' => 't_cotiza',  'label' => 'Cotización / afiliación',   'area' => 'comercial',  'dur_minutos' => 20, 'requiere_tipo_sala' => '["comercial"]',                'activo' => 1],
            ['id' => 't_preqx',   'label' => 'Valoración pre-quirúrgica', 'area' => 'comercial',  'dur_minutos' => 15, 'requiere_tipo_sala' => '["comercial"]',                'activo' => 1],
        ], ['id'], ['label', 'area', 'dur_minutos', 'requiere_tipo_sala', 'activo']);

        $horarios = [
            ['medico_id' => 'md_dra_carolina_ramirez', 'dia_semana' => 1, 'hora_ini' => '08:00:00', 'hora_fin' => '13:00:00', 'sede_id' => 'ceibos', 'activo' => 1],
            ['medico_id' => 'md_dra_carolina_ramirez', 'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '14:00:00', 'sede_id' => 'ceibos', 'activo' => 1],
        ];

        if (DB::table('agenda_horarios')->count() === 0) {
            DB::table('agenda_horarios')->insert($horarios);
        }
    }
}
