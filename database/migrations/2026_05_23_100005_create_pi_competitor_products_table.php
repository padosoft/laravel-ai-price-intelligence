<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.competitor_products', 'pi_competitor_products');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->index();
            $blueprint->unsignedBigInteger('monitoring_target_id')->index();
            $blueprint->unsignedBigInteger('competitor_source_id')->nullable()->index();
            $blueprint->text('url');
            $blueprint->string('external_ref')->nullable();
            $blueprint->string('match_status', 20)->default('suggested');
            $blueprint->unsignedTinyInteger('match_confidence')->nullable();
            $blueprint->string('match_method', 30)->nullable();
            $blueprint->unsignedBigInteger('validated_by')->nullable();
            $blueprint->timestamp('validated_at')->nullable();
            $blueprint->timestamp('last_seen_at')->nullable();
            $blueprint->timestamp('dead_since')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['tenant_id', 'match_status'], 'pi_cp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.competitor_products', 'pi_competitor_products'));
    }
};
