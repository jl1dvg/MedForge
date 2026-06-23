<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class VerifySchemaIntegrity extends Command
{
    protected $signature = 'app:verify-schema';

    protected $description = 'Verifica que todas las tablas creadas por migraciones existan realmente en la base de datos';

    public function handle(): int
    {
        $migrationsPath = database_path('migrations');
        $missing = [];
        $checked = 0;

        foreach (File::files($migrationsPath) as $file) {
            $contents = File::get($file->getPathname());

            if (!preg_match_all('/Schema::create\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $table) {
                $checked++;

                if (!Schema::hasTable($table) && !in_array($table, $missing, true)) {
                    $missing[] = $table;
                }
            }
        }

        if (!empty($missing)) {
            $this->error(sprintf(
                'Faltan %d tabla(s) que las migraciones dicen haber creado: %s',
                count($missing),
                implode(', ', $missing)
            ));

            return self::FAILURE;
        }

        $this->info("OK: {$checked} tabla(s) verificadas, todas existen.");

        return self::SUCCESS;
    }
}
