<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.products', 'pi_products');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->index();
            $blueprint->string('external_id');
            $blueprint->string('sku')->nullable();
            $blueprint->string('gtin', 14)->nullable();
            $blueprint->string('mpn')->nullable();
            $blueprint->string('brand')->nullable();
            $blueprint->string('model')->nullable();
            $blueprint->string('name');
            $blueprint->json('attributes')->nullable();
            $blueprint->json('images')->nullable();
            $blueprint->json('categories')->nullable();
            $blueprint->unsignedBigInteger('our_price_cents')->nullable();
            $blueprint->string('currency', 3)->nullable();
            $blueprint->string('base_country', 2)->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->unique(['tenant_id', 'external_id'], 'pi_products_tenant_ext_uq');
            $blueprint->index(['tenant_id', 'gtin'], 'pi_products_tenant_gtin_idx');
            $blueprint->index(['tenant_id', 'brand'], 'pi_products_tenant_brand_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.products', 'pi_products'));
    }
};
