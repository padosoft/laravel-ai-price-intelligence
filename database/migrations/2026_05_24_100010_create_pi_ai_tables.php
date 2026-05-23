<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $forecasts = (string) config('price-intelligence.tables.forecasts', 'pi_forecasts');
        $anomalies = (string) config('price-intelligence.tables.anomalies', 'pi_anomalies');
        $logs = (string) config('price-intelligence.tables.ai_decision_logs', 'pi_ai_decision_logs');

        if (! Schema::hasTable($forecasts)) {
            Schema::create($forecasts, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->unsignedBigInteger('competitor_product_id')->index();
                $b->unsignedSmallInteger('horizon_days');
                $b->unsignedBigInteger('forecast_price_cents');
                $b->unsignedBigInteger('ci_low_cents')->nullable();
                $b->unsignedBigInteger('ci_high_cents')->nullable();
                $b->string('model_version', 50)->default('statistical-v1');
                $b->boolean('is_ai_generated')->default(true);
                $b->timestamp('generated_at');
                $b->timestamps();
                $b->index(['competitor_product_id', 'horizon_days'], 'pi_fc_cp_h_idx');
            });
        }

        if (! Schema::hasTable($anomalies)) {
            Schema::create($anomalies, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->unsignedBigInteger('competitor_product_id')->index();
                $b->string('type', 30);
                $b->string('severity', 20)->default('medium');
                $b->json('evidence')->nullable();
                // Consistent with forecasts: anomalies are outputs of the AI/intelligence
                // layer. A host using a purely deterministic path may override per row.
                $b->boolean('is_ai_generated')->default(true);
                $b->timestamp('detected_at');
                $b->unsignedBigInteger('acknowledged_by')->nullable();
                $b->timestamp('acknowledged_at')->nullable();
                $b->timestamps();
                $b->index(['tenant_id', 'type'], 'pi_anom_type_idx');
            });
        }

        if (! Schema::hasTable($logs)) {
            Schema::create($logs, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->string('subject_type', 100)->nullable();
                $b->unsignedBigInteger('subject_id')->nullable();
                $b->string('feature', 50);
                $b->string('model', 100)->nullable();
                $b->string('model_version', 50)->nullable();
                $b->string('input_hash', 64)->nullable();
                $b->json('output')->nullable();
                $b->unsignedSmallInteger('confidence')->nullable();
                $b->unsignedBigInteger('cost_micros')->nullable();
                $b->boolean('human_reviewed')->default(false);
                $b->timestamps();
                $b->index(['tenant_id', 'feature'], 'pi_aidl_feat_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.forecasts', 'pi_forecasts'));
        Schema::dropIfExists((string) config('price-intelligence.tables.anomalies', 'pi_anomalies'));
        Schema::dropIfExists((string) config('price-intelligence.tables.ai_decision_logs', 'pi_ai_decision_logs'));
    }
};
