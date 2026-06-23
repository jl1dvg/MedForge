<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestProtocoloHuella extends Command
{
    protected $signature = 'test:protocolo-huella
                            {protocolo_id : ID del protocolo de prueba}
                            {usuario_id   : ID del usuario de prueba}';

    protected $description = '[PRUEBA TEMPORAL] Valida upsert en protocolo_huellas. Eliminar después de verificar.';

    public function handle(): int
    {
        $protocoloId = (int) $this->argument('protocolo_id');
        $usuarioId   = (int) $this->argument('usuario_id');
        $evento      = 'test_huella';

        // Verificar que la tabla existe
        if (!DB::getSchemaBuilder()->hasTable('protocolo_huellas')) {
            $this->error('Tabla protocolo_huellas no existe. Ejecuta las migraciones primero.');
            return self::FAILURE;
        }

        $this->info("Protocolo ID : $protocoloId");
        $this->info("Usuario ID   : $usuarioId");
        $this->line('');

        // --- PASO 1: primera escritura ---
        $this->info('[1] Primera escritura...');
        DB::statement(
            'INSERT INTO protocolo_huellas
                (protocolo_id, usuario_id, evento, creado_en, actualizado_en)
             VALUES
                (:protocolo_id, :usuario_id, :evento, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                evento         = VALUES(evento),
                actualizado_en = NOW()',
            ['protocolo_id' => $protocoloId, 'usuario_id' => $usuarioId, 'evento' => $evento]
        );

        $registro1 = DB::selectOne(
            'SELECT * FROM protocolo_huellas WHERE protocolo_id = ? AND usuario_id = ?',
            [$protocoloId, $usuarioId]
        );

        if (!$registro1) {
            $this->error('No se creó el registro. Revisar la tabla y la migración.');
            return self::FAILURE;
        }

        $this->line("  creado_en     : {$registro1->creado_en}");
        $this->line("  actualizado_en: {$registro1->actualizado_en}");
        $this->line("  evento        : {$registro1->evento}");
        $this->info('  OK — registro creado');
        $this->line('');

        // Pausa de 1s para que actualizado_en sea diferente
        sleep(1);

        // --- PASO 2: segunda escritura (mismo usuario + protocolo) ---
        $this->info('[2] Segunda escritura (mismo usuario)...');
        DB::statement(
            'INSERT INTO protocolo_huellas
                (protocolo_id, usuario_id, evento, creado_en, actualizado_en)
             VALUES
                (:protocolo_id, :usuario_id, :evento, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                evento         = VALUES(evento),
                actualizado_en = NOW()',
            ['protocolo_id' => $protocoloId, 'usuario_id' => $usuarioId, 'evento' => $evento]
        );

        $registro2 = DB::selectOne(
            'SELECT * FROM protocolo_huellas WHERE protocolo_id = ? AND usuario_id = ?',
            [$protocoloId, $usuarioId]
        );

        $conteo = DB::selectOne(
            'SELECT COUNT(*) AS total FROM protocolo_huellas WHERE protocolo_id = ? AND usuario_id = ?',
            [$protocoloId, $usuarioId]
        );

        $this->line("  creado_en     : {$registro2->creado_en}");
        $this->line("  actualizado_en: {$registro2->actualizado_en}");
        $this->line("  registros totales para este par: {$conteo->total}");

        if ((int) $conteo->total !== 1) {
            $this->error("  FALLO — se esperaba 1 registro, hay {$conteo->total}.");
            $this->cleanUp($protocoloId, $usuarioId, $evento);
            return self::FAILURE;
        }

        if ($registro2->creado_en !== $registro1->creado_en) {
            $this->warn('  AVISO — creado_en cambió (debería mantenerse igual).');
        } else {
            $this->info('  OK — creado_en se mantuvo igual');
        }

        if ($registro2->actualizado_en === $registro1->actualizado_en) {
            $this->warn('  AVISO — actualizado_en no cambió (puede ser que el segundo se ejecutó en el mismo segundo).');
        } else {
            $this->info('  OK — actualizado_en actualizado');
        }

        $this->line('');

        // --- PASO 3: consulta último editor ---
        $this->info('[3] Consulta último editor del protocolo...');
        $ultimoEditor = DB::selectOne(
            'SELECT ph.*, u.name AS usuario_nombre
             FROM protocolo_huellas ph
             LEFT JOIN users u ON u.id = ph.usuario_id
             WHERE ph.protocolo_id = ?
             ORDER BY ph.actualizado_en DESC
             LIMIT 1',
            [$protocoloId]
        );

        if ($ultimoEditor) {
            $this->line("  Último editor : {$ultimoEditor->usuario_nombre} (ID {$ultimoEditor->usuario_id})");
            $this->line("  Última edición: {$ultimoEditor->actualizado_en}");
            $this->info('  OK');
        } else {
            $this->warn('  No se encontró editor (usuarios pueden no estar relacionados en esta BD).');
        }

        $this->line('');

        // --- LIMPIEZA ---
        $this->cleanUp($protocoloId, $usuarioId, $evento);

        $this->line('');
        $this->info('Prueba completada. Registro de prueba eliminado.');

        return self::SUCCESS;
    }

    private function cleanUp(int $protocoloId, int $usuarioId, string $evento): void
    {
        $deleted = DB::delete(
            "DELETE FROM protocolo_huellas WHERE protocolo_id = ? AND usuario_id = ? AND evento = '$evento'",
            [$protocoloId, $usuarioId]
        );
        $this->line("  Limpieza: $deleted registro(s) eliminado(s) de protocolo_huellas.");
    }
}
