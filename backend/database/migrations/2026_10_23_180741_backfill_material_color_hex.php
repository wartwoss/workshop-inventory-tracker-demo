<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $colors = [
            'oak-natural' => '#c49a6c',
            'walnut' => '#5c4033',
            'pine' => '#e8d4a8',
            'brass' => '#b5a642',
            'steel' => '#9ca3af',
            'ceramic-white' => '#f5f5f5',
            'fabric-grey' => '#7a7a7a',
            'glue' => '#ffffff',
        ];

        foreach ($colors as $visualKey => $color) {
            DB::table('materials')
                ->where('visual_key', $visualKey)
                ->whereNull('color_hex')
                ->update(['color_hex' => $color]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: we cannot safely distinguish backfilled values from user edits.
    }
};
