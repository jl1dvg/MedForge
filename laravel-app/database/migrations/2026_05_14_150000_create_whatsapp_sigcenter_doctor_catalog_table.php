<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'whatsapp_sigcenter_doctor_catalog';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_user_id')->nullable()->index();
            $table->string('trabajador_id', 64)->index();
            $table->string('doctor_nombre', 191);
            $table->string('doctor_email', 191)->nullable();
            $table->text('doctor_profile_photo')->nullable();
            $table->string('especialidad', 191)->nullable();
            $table->string('subespecialidad', 191)->index();
            $table->string('sede_id', 64)->index();
            $table->string('sede_nombre', 191);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['trabajador_id', 'subespecialidad', 'sede_id'], 'wa_sigcenter_doc_catalog_unique');
        });

        $this->seedFromUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function seedFromUsers(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $rows = DB::table('users')
            ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador', 'sede'])
            ->whereNotNull('id_trabajador')
            ->whereNotNull('subespecialidad')
            ->where('subespecialidad', '<>', '')
            ->where(function ($query): void {
                $query->where('especialidad', 'Cirujano Oftalmólogo')
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
            })
            ->orderBy('id')
            ->get();

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            foreach ($this->expandSedes((string) ($row->sede ?? '')) as $sede) {
                $key = implode('|', [
                    (string) $row->id_trabajador,
                    trim((string) $row->subespecialidad),
                    $sede['sede_id'],
                ]);

                $payload[$key] = [
                    'source_user_id' => $row->id !== null ? (int) $row->id : null,
                    'trabajador_id' => trim((string) $row->id_trabajador),
                    'doctor_nombre' => trim((string) ($row->nombre ?? '')),
                    'doctor_email' => $this->nullableString($row->email ?? null, 191),
                    'doctor_profile_photo' => $this->nullableText($row->profile_photo ?? null),
                    'especialidad' => $this->nullableString($row->especialidad ?? null, 191),
                    'subespecialidad' => trim((string) $row->subespecialidad),
                    'sede_id' => $sede['sede_id'],
                    'sede_nombre' => $sede['sede_nombre'],
                    'active' => true,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($payload !== []) {
            DB::table(self::TABLE)->insert(array_values($payload));
        }
    }

    /**
     * @return array<int, array{sede_id:string,sede_nombre:string}>
     */
    private function expandSedes(string $rawSede): array
    {
        $value = strtoupper(str_replace([' ', '-', '_'], '', trim($rawSede)));

        return match ($value) {
            'CEIBOS' => [['sede_id' => '16', 'sede_nombre' => 'Ceibos']],
            'VILLACLUB' => [['sede_id' => '1', 'sede_nombre' => 'Villa Club']],
            'CEIBOSVILLACLUB' => [
                ['sede_id' => '16', 'sede_nombre' => 'Ceibos'],
                ['sede_id' => '1', 'sede_nombre' => 'Villa Club'],
            ],
            default => [],
        };
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : mb_substr($string, 0, $maxLength, 'UTF-8');
    }

    private function nullableText(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
};
