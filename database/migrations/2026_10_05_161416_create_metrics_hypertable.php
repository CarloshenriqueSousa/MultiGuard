<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared("
            CREATE TABLE metrics (
                time      timestamptz      NOT NULL,
                series_id bigint           NOT NULL REFERENCES series(id) ON DELETE CASCADE,
                value     double precision NOT NULL,
                PRIMARY KEY (series_id, time)
            )
        ");

        DB::unprepared("SELECT create_hypertable('metrics', 'time', chunk_time_interval => INTERVAL '1 day')");

        DB::unprepared("
            ALTER TABLE metrics SET (
                timescaledb.compress,
                timescaledb.compress_segmentby = 'series_id',
                timescaledb.compress_orderby = 'time DESC'
            )
        ");
        DB::unprepared("SELECT add_compression_policy('metrics', INTERVAL '2 days')");

        DB::unprepared("SELECT add_retention_policy('metrics', INTERVAL '14 days')");
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS metrics CASCADE');
    }
};
