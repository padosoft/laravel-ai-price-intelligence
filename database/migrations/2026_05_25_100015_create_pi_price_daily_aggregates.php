<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily price aggregates (min/max/avg) materialized nightly from raw observations
 * (piprice:aggregates:daily). Lets dashboards/exports read long histories cheaply
 * while raw observations age out under retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.price_daily_aggregates', 'pi_price_daily_aggregates');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->date('day');
            $b->unsignedBigInteger('min_price_cents')->nullable();
            $b->unsignedBigInteger('max_price_cents')->nullable();
            $b->unsignedBigInteger('avg_price_cents')->nullable();
            $b->unsignedInteger('samples')->default(0);
            // Non-nullable with an empty-string sentinel: a nullable component in the unique key
            // would let SQL treat NULLs as distinct and admit duplicate (cp, day) rows.
            $b->string('currency', 3)->default('');
            $b->timestamps();
            $b->unique(['competitor_product_id', 'day', 'currency'], 'pi_pda_cp_day_cur_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.price_daily_aggregates', 'pi_price_daily_aggregates'));
    }
};
