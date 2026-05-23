<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.api_keys', 'pi_api_keys');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->index();
            $blueprint->string('name');
            $blueprint->string('key_hash', 64)->unique('pi_apikey_hash_uq');
            $blueprint->json('scopes')->nullable();
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->timestamp('revoked_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.api_keys', 'pi_api_keys'));
    }
};
