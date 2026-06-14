<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            // Traffic fines
            'daily_fine_stats',
            'fine_checks',
            'traffic_fine_violations',
            'traffic_fines',

            // Telemetry / fleet tracking
            'telemetry_points_archive',
            'telemetry_events',
            'telemetry_history',
            'telemetry_recent',
            'telemetry_points',
            'vehicle_geofence_states',
            'geofences',
            'vehicle_routes',
            'routes',
            'vehicle_daily_stats',
            'fuel_calibrations',
            'vehicle_snapshots',

            // Vehicle / driver / trailer management
            'vehicle_insurances',
            'vehicle_inspections',
            'wialon_units',
            'driver_vehicle_assignments',
            'trailer_assignments',
            'vehicles',
            'trailers',
            'drivers',

            // Trip / order / client
            'trip_histories',
            'requisition_signatures',
            'requisition_proofs',
            'payments',
            'requisitions',
            'trips',
            'orders',
            'clients',

            // Expense
            'expense_types',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Tables are intentionally dropped; recreation requires restoring original migrations.
    }
};
