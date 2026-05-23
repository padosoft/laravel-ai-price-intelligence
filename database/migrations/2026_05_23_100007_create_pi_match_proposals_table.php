<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.match_proposals', 'pi_match_proposals');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->index();
            $blueprint->unsignedBigInteger('monitoring_target_id')->index();
            $blueprint->unsignedBigInteger('competitor_source_id')->nullable();
            $blueprint->text('candidate_url');
            $blueprint->json('evidence')->nullable();
            $blueprint->unsignedTinyInteger('confidence')->default(0);
            $blueprint->string('source', 20)->default('ai');
            $blueprint->string('status', 20)->default('pending');
            $blueprint->unsignedBigInteger('reviewer_id')->nullable();
            $blueprint->timestamp('reviewed_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['tenant_id', 'status'], 'pi_mp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.match_proposals', 'pi_match_proposals'));
    }
};
