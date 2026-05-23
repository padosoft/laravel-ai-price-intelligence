<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.monitoring_targets', 'pi_monitoring_targets');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->index();
            $blueprint->unsignedBigInteger('product_id')->index();
            $blueprint->string('country', 2);
            $blueprint->string('locale', 10)->nullable();
            $blueprint->string('frequency_preset', 20)->default('daily');
            $blueprint->string('cron_custom')->nullable();
            $blueprint->string('status', 20)->default('active');
            $blueprint->unsignedSmallInteger('priority')->default(100);
            $blueprint->json('options')->nullable();
            $blueprint->timestamp('last_check_at')->nullable();
            $blueprint->timestamp('next_check_at')->nullable();
            $blueprint->float('backoff_factor')->default(1);
            $blueprint->timestamps();

            $blueprint->unique(['tenant_id', 'product_id', 'country'], 'pi_targets_uq');
            $blueprint->index(['status', 'next_check_at'], 'pi_targets_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.monitoring_targets', 'pi_monitoring_targets'));
    }
};
