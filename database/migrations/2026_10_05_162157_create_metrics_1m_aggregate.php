<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared("
            CREATE MATERIALIZED VIEW metrics_1m
            WITH (timescaledb.continuous) AS
            SELECT series_id,
                   time_bucket(INTERVAL '1 minute', time) AS bucket,
                   avg(value) AS avg,
                   min(value) AS min,
                   max(value) AS max,
                   count(*)   AS samples
            FROM metrics
            GROUP BY series_id, bucket
            WITH NO DATA
        ");

        DB::unprepared("
            SELECT add_continuous_aggregate_policy('metrics_1m',
                start_offset      => INTERVAL '2 hours',
                end_offset        => INTERVAL '1 minute',
                schedule_interval => INTERVAL '1 minute')
        ");

        DB::unprepared("SELECT add_retention_policy('metrics_1m', INTERVAL '13 months')");

        DB::unprepared("ALTER MATERIALIZED VIEW metrics_1m SET (timescaledb.materialized_only = false)");
    }

    public function down(): void
    {
        DB::unprepared('DROP MATERIALIZED VIEW IF EXISTS metrics_1m CASCADE');
    }
};
