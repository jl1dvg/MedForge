<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgendaV3Seeder extends Seeder
{
    public function run(): void
    {
        DB::table('agenda_sedes')->upsert([
            ['id' => 'ceibos',    'label' => 'Ceibos',     'abrev' => 'CB', 'apertura' => '08:00:00', 'cierre' => '18:00:00', 'activo' => 1],
            ['id' => 'villaclub', 'label' => 'Villa Club', 'abrev' => 'VC', 'apertura' => '08:00:00', 'cierre' => '17:00:00', 'activo' => 1],
        ], ['id'], ['label', 'abrev', 'apertura', 'cierre', 'activo']);

        DB::table('agenda_medicos')->upsert([
            ['id' => 'm_ramirez',  'nombre' => 'Dra. Carolina Ramírez',       'especialidad' => 'Retina y catarata',         'areas' => '["consulta","quirurgico"]', 'sede_id' => 'ceibos',    'color' => '#5156be', 'iniciales' => 'CR', 'activo' => 1],
            ['id' => 'm_salazar',  'nombre' => 'Dr. Marco Salazar',           'especialidad' => 'Vítreo-retina',             'areas' => '["quirurgico","consulta"]', 'sede_id' => 'ceibos',    'color' => '#d34b5b', 'iniciales' => 'MS', 'activo' => 1],
            ['id' => 'm_veintim',  'nombre' => 'Dra. Valeria Veintimilla',    'especialidad' => 'Glaucoma',                  'areas' => '["consulta","quirurgico"]', 'sede_id' => 'ceibos',    'color' => '#0863be', 'iniciales' => 'VV', 'activo' => 1],
            ['id' => 'm_vargas',   'nombre' => 'Dr. Andrés Vargas',           'especialidad' => 'Córnea y segmento ant.',    'areas' => '["consulta","imagenes"]',   'sede_id' => 'ceibos',    'color' => '#7c4dff', 'iniciales' => 'AV', 'activo' => 1],
            ['id' => 'm_encalada', 'nombre' => 'Lic. Daniela Encalada',       'especialidad' => 'Optometría',                'areas' => '["consulta"]',             'sede_id' => 'ceibos',    'color' => '#1f9d7a', 'iniciales' => 'DE', 'activo' => 1],
            ['id' => 'm_andrade',  'nombre' => 'Dr. Jorge Andrade',           'especialidad' => 'Oculoplástica',             'areas' => '["consulta","quirurgico"]', 'sede_id' => 'villaclub', 'color' => '#ffa800', 'iniciales' => 'JA', 'activo' => 1],
            ['id' => 'm_mendoza',  'nombre' => 'Dra. Paula Mendoza',          'especialidad' => 'Oftalmología pediátrica',   'areas' => '["consulta"]',             'sede_id' => 'villaclub', 'color' => '#3d7ac7', 'iniciales' => 'PM', 'activo' => 1],
        ], ['id'], ['nombre', 'especialidad', 'areas', 'sede_id', 'color', 'iniciales', 'activo']);

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

        // Horarios base — jueves (dia=4) como ejemplo completo; lunes (dia=1) para Ramírez
        $horarios = [
            ['medico_id' => 'm_ramirez',  'dia_semana' => 1, 'hora_ini' => '08:00:00', 'hora_fin' => '13:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_ramirez',  'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '14:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_ramirez',  'dia_semana' => 4, 'hora_ini' => '15:00:00', 'hora_fin' => '18:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_salazar',  'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '13:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_veintim',  'dia_semana' => 4, 'hora_ini' => '09:00:00', 'hora_fin' => '17:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_vargas',   'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '16:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_encalada', 'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '18:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
            ['medico_id' => 'm_andrade',  'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '13:00:00', 'sede_id' => 'villaclub', 'activo' => 1],
            ['medico_id' => 'm_mendoza',  'dia_semana' => 4, 'hora_ini' => '13:00:00', 'hora_fin' => '17:00:00', 'sede_id' => 'villaclub', 'activo' => 1],
        ];

        // Insert only if table is empty to allow re-running without duplicates
        if (DB::table('agenda_horarios')->count() === 0) {
            DB::table('agenda_horarios')->insert($horarios);
        }
    }
}
