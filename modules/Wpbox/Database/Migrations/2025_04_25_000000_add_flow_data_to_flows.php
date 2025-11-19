<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFlowDataToFlows extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->flowsTableExists()) {
            return;
        }

        if (!Schema::hasColumn('flows', 'flow_data')) {
            $hasConnectionsColumn = Schema::hasColumn('flows', 'connections');

            Schema::table('flows', function (Blueprint $table) use ($hasConnectionsColumn) {
                $column = $table->longText('flow_data')->nullable();

                if ($hasConnectionsColumn) {
                    $column->after('connections');
                }
            });
        } else {
            DB::statement('ALTER TABLE `flows` MODIFY `flow_data` LONGTEXT NULL');
        }

        if (Schema::hasColumn('flows', 'nodes') || Schema::hasColumn('flows', 'connections')) {
            $this->migrateLegacyData();
        }

        if (Schema::hasColumn('flows', 'nodes')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->dropColumn('nodes');
            });
        }

        if (Schema::hasColumn('flows', 'connections')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->dropColumn('connections');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->flowsTableExists()) {
            return;
        }

        if (!Schema::hasColumn('flows', 'nodes')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->text('nodes')->nullable()->after('company_id');
            });
        }

        if (!Schema::hasColumn('flows', 'connections')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->text('connections')->nullable()->after('nodes');
            });
        }

        if (Schema::hasColumn('flows', 'flow_data')) {
            DB::table('flows')
                ->select(['id', 'flow_data'])
                ->orderBy('id')
                ->chunkById(100, function ($flows) {
                    foreach ($flows as $flow) {
                        $nodes = null;
                        $connections = null;

                        if (!empty($flow->flow_data)) {
                            $decoded = json_decode($flow->flow_data, true);

                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                if (array_key_exists('nodes', $decoded)) {
                                    $nodes = json_encode($decoded['nodes'], JSON_UNESCAPED_UNICODE);
                                }

                                if (array_key_exists('edges', $decoded)) {
                                    $connections = json_encode($decoded['edges'], JSON_UNESCAPED_UNICODE);
                                }
                            }
                        }

                        DB::table('flows')->where('id', $flow->id)->update([
                            'nodes' => $nodes,
                            'connections' => $connections,
                        ]);
                    }
                });

            Schema::table('flows', function (Blueprint $table) {
                $table->dropColumn('flow_data');
            });
        }
    }

    /**
     * Move the legacy node/connection data into the new flow_data column.
     */
    protected function migrateLegacyData(): void
    {
        if (!$this->flowsTableExists()) {
            return;
        }

        $normalize = function ($value) {
            if ($value === null) {
                return null;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return $value;
        };

        DB::table('flows')
            ->select(['id', 'flow_data', 'nodes', 'connections'])
            ->orderBy('id')
            ->chunkById(100, function ($flows) use ($normalize) {
                foreach ($flows as $flow) {
                    if (!empty($flow->flow_data)) {
                        continue;
                    }

                    $nodes = $normalize($flow->nodes);
                    $edges = $normalize($flow->connections);

                    if (empty($nodes) && empty($edges)) {
                        continue;
                    }

                    $payload = [
                        'nodes' => $nodes ?? [],
                        'edges' => $edges ?? [],
                    ];

                    DB::table('flows')->where('id', $flow->id)->update([
                        'flow_data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    /**
     * Determine whether the flows table exists before touching it.
     */
    protected function flowsTableExists(): bool
    {
        try {
            return Schema::hasTable('flows');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
