<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.competitor_sources', 'pi_competitor_sources');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('host');
            $blueprint->string('display_name')->nullable();
            $blueprint->string('country', 2)->nullable();
            $blueprint->string('adapter_code', 30)->default('generic');
            $blueprint->string('robots_policy', 20)->default('respect');
            $blueprint->unsignedInteger('rate_limit_rpm')->nullable();
            $blueprint->json('options')->nullable();
            $blueprint->timestamps();

            $blueprint->unique('host', 'pi_sources_host_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.competitor_sources', 'pi_competitor_sources'));
    }
};
