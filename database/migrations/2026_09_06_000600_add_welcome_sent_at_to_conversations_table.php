<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('conversations') && !Schema::hasColumn('conversations', 'welcome_sent_at')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->timestamp('welcome_sent_at')->nullable()->after('last_message_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'welcome_sent_at')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropColumn('welcome_sent_at');
            });
        }
    }
};
