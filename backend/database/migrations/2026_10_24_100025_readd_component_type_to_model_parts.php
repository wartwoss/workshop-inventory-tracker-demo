<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-add the component_type column that was lost during a partial rollback
     * of the builder-fields migration on SQLite.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('model_parts', 'component_type')) {
            Schema::table('model_parts', function (Blueprint $table) {
                $table->string('component_type', 50)
                    ->nullable()
                    ->after('code');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasColumn('model_parts', 'component_type')) {
            Schema::table('model_parts', function (Blueprint $table) {
                $table->dropColumn('component_type');
            });
        }
    }
};
