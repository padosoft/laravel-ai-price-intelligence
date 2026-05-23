<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.tenants', 'pi_tenants');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('code', 100);
            $blueprint->string('name');
            $blueprint->json('settings')->nullable();
            $blueprint->timestamps();

            $blueprint->unique('code', 'pi_tenant_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.tenants', 'pi_tenants'));
    }
};
