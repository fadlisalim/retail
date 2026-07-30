<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer RFQ intake: qualification (who is asking, how much authority),
 * project context (stage, type, funding, scale), commercial band (budget) and
 * the extra deliverables customers usually ask for (survey, tender documents).
 * Type-specific engineering answers live in `requirements` (JSON) so adding a
 * new project type never needs another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('requester_role', 30)->nullable()->after('npwp');
            $table->string('decision_role', 30)->nullable()->after('requester_role');
            $table->string('project_type', 30)->nullable()->after('project_name');
            $table->string('project_status', 30)->nullable()->after('project_type');
            $table->string('funding_source', 30)->nullable()->after('project_status');
            $table->string('budget_range', 30)->nullable()->after('funding_source');
            $table->string('unit_scale', 80)->nullable()->after('project_location'); // "20 titik", "±150 KK"
            $table->boolean('needs_survey')->default(false)->after('needs_installation');
            $table->boolean('needs_tender_docs')->default(false)->after('needs_survey');
            $table->json('requirements')->nullable()->after('technical_notes');

            // Admin list filters on stage/budget when triaging the queue.
            $table->index(['project_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['project_status', 'created_at']);
            $table->dropColumn([
                'requester_role', 'decision_role', 'project_type', 'project_status',
                'funding_source', 'budget_range', 'unit_scale',
                'needs_survey', 'needs_tender_docs', 'requirements',
            ]);
        });
    }
};
