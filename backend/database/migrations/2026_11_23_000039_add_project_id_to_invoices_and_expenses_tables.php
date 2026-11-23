<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'project_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('deal_id')->constrained('projects')->nullOnDelete();
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('expenses', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->nullOnDelete();
                }
                if (!Schema::hasColumn('expenses', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('project_id')->constrained('customers')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'project_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'project_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }
    }
};
