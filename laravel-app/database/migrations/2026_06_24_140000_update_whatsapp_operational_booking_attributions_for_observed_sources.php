<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_operational_booking_attributions')) {
            return;
        }

        Schema::table('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'booking_source')) {
                $table->string('booking_source', 48)->default('bot_api')->after('id')->index('woba_booking_source_idx');
            }

            if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'observed_booking_key')) {
                $table->string('observed_booking_key', 191)->nullable()->after('booking_source');
            }

            if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'form_id')) {
                $table->unsignedBigInteger('form_id')->nullable()->after('booking_id')->index('woba_form_id_idx');
            }
        });

        $this->backfillObservedBookingKeys();
        $this->makeBookingIdNullable();
        $this->ensureObservedBookingKeyUnique();
    }

    public function down(): void
    {
        // Intentionally non-destructive: these columns are part of the attribution identity.
    }

    private function backfillObservedBookingKeys(): void
    {
        if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'observed_booking_key')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement(
                "UPDATE whatsapp_operational_booking_attributions
                 SET observed_booking_key = 'whatsapp_sigcenter_bookings:' || booking_id
                 WHERE observed_booking_key IS NULL AND booking_id IS NOT NULL"
            );

            return;
        }

        DB::statement(
            "UPDATE whatsapp_operational_booking_attributions
             SET observed_booking_key = CONCAT('whatsapp_sigcenter_bookings:', booking_id)
             WHERE observed_booking_key IS NULL AND booking_id IS NOT NULL"
        );
    }

    private function makeBookingIdNullable(): void
    {
        if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'booking_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            DB::statement('ALTER TABLE whatsapp_operational_booking_attributions DROP INDEX whatsapp_operational_booking_attributions_booking_id_unique');
        } catch (\Throwable) {
        }

        try {
            DB::statement('ALTER TABLE whatsapp_operational_booking_attributions MODIFY booking_id BIGINT UNSIGNED NULL');
        } catch (\Throwable) {
        }

        $this->addIndexIfMissing('woba_booking_id_idx', ['booking_id']);
    }

    private function ensureObservedBookingKeyUnique(): void
    {
        if (!Schema::hasColumn('whatsapp_operational_booking_attributions', 'observed_booking_key')) {
            return;
        }

        $this->addUniqueIfMissing('woba_observed_booking_key_uniq', ['observed_booking_key']);
    }

    /**
     * @param array<int,string> $columns
     */
    private function addIndexIfMissing(string $name, array $columns): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        Schema::table('whatsapp_operational_booking_attributions', function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    /**
     * @param array<int,string> $columns
     */
    private function addUniqueIfMissing(string $name, array $columns): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        Schema::table('whatsapp_operational_booking_attributions', function (Blueprint $table) use ($columns, $name): void {
            $table->unique($columns, $name);
        });
    }

    private function indexExists(string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', 'whatsapp_operational_booking_attributions')
                ->where('index_name', $name)
                ->exists();
        }

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('whatsapp_operational_booking_attributions')") as $index) {
                if (($index->name ?? null) === $name) {
                    return true;
                }
            }
        }

        return false;
    }
};
