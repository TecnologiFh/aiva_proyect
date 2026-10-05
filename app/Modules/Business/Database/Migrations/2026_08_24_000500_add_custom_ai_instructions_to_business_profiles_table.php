<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('business_profiles', 'custom_ai_instructions')) {
                    $table->text('custom_ai_instructions')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('business_profiles', 'custom_ai_instructions')) {
                    $table->dropColumn('custom_ai_instructions');
                }
            });
        }
    }
};
