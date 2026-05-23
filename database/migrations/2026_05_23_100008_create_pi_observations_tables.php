<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-series and audit tables. These are the high-volume tables and the schema
 * is intentionally partition-friendly (captured_at present, no cross-table FKs)
 * so monthly partitioning can be enabled later (planned PartitionManager, see
 * docs/PROJECT.md §6 / phase 11). No partitioning is applied by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $t = static fn (string $key, string $default): string => (string) config("price-intelligence.tables.{$key}", $default);

        $this->create($t('price_observations', 'pi_price_observations'), function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->timestamp('captured_at')->index();
            $b->unsignedBigInteger('price_cents')->nullable();
            $b->string('currency', 3)->nullable();
            $b->unsignedBigInteger('price_base_cents')->nullable();
            $b->unsignedBigInteger('shipping_cents')->nullable();
            $b->boolean('available')->default(true);
            $b->string('raw_price_text')->nullable();
            $b->unsignedBigInteger('fetch_log_id')->nullable();
            $b->timestamps();
            $b->index(['competitor_product_id', 'captured_at'], 'pi_po_cp_time_idx');
        });

        $this->create($t('content_snapshots', 'pi_content_snapshots'), function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->timestamp('captured_at')->index();
            $b->string('title')->nullable();
            $b->longText('description_md')->nullable();
            $b->json('attributes')->nullable();
            $b->json('og')->nullable();
            $b->json('jsonld')->nullable();
            $b->json('images')->nullable();
            $b->string('html_hash', 64)->nullable();
            $b->float('dom_diff_score')->nullable();
            $b->timestamps();
        });

        $this->create($t('stock_observations', 'pi_stock_observations'), function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->timestamp('captured_at')->index();
            $b->boolean('available')->default(true);
            $b->unsignedInteger('qty_estimate')->nullable();
            $b->boolean('buybox_winner')->nullable();
            $b->string('seller_name')->nullable();
            $b->string('seller_rating')->nullable();
            $b->timestamps();
        });

        $this->create($t('promo_observations', 'pi_promo_observations'), function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->timestamp('captured_at')->index();
            $b->string('promo_type', 20)->default('none');
            $b->timestamp('valid_from')->nullable();
            $b->timestamp('valid_to')->nullable();
            $b->text('condition_text')->nullable();
            $b->float('effective_discount_pct')->nullable();
            $b->timestamps();
        });

        $this->create($t('fetch_logs', 'pi_fetch_logs'), function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_source_id')->nullable();
            $b->text('url');
            $b->string('method', 10)->default('GET');
            $b->unsignedSmallInteger('status')->nullable();
            $b->unsignedInteger('latency_ms')->nullable();
            $b->string('ua')->nullable();
            $b->string('ip_egress', 64)->nullable();
            $b->boolean('proxy_used')->default(false);
            $b->text('error')->nullable();
            $b->string('body_hash', 64)->nullable();
            $b->unsignedInteger('response_bytes')->nullable();
            $b->boolean('robots_allowed')->default(true);
            $b->string('search_provider')->nullable();
            $b->string('driver', 50)->nullable();
            $b->timestamp('captured_at')->index();
            $b->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['price_observations', 'content_snapshots', 'stock_observations', 'promo_observations', 'fetch_logs'] as $key) {
            $default = 'pi_' . $key;
            Schema::dropIfExists((string) config("price-intelligence.tables.{$key}", $default));
        }
    }

    private function create(string $table, callable $definition): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $definition);
    }
};
