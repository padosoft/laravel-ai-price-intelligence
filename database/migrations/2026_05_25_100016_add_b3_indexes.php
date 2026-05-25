<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B3 hot-table index review: composite indexes matching the new query patterns —
 * stock/promo history per competitor over time, and AI-decision subject lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->index('stock_observations', 'pi_stock_observations', ['competitor_product_id', 'captured_at'], 'pi_so_cp_time_idx');
        $this->index('promo_observations', 'pi_promo_observations', ['competitor_product_id', 'captured_at'], 'pi_promo_cp_time_idx');
        $this->index('ai_decision_logs', 'pi_ai_decision_logs', ['tenant_id', 'subject_type', 'subject_id'], 'pi_aidl_subj_idx');
    }

    public function down(): void
    {
        $this->dropIndex('stock_observations', 'pi_stock_observations', 'pi_so_cp_time_idx');
        $this->dropIndex('promo_observations', 'pi_promo_observations', 'pi_promo_cp_time_idx');
        $this->dropIndex('ai_decision_logs', 'pi_ai_decision_logs', 'pi_aidl_subj_idx');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function index(string $key, string $default, array $columns, string $name): void
    {
        $table = (string) config("price-intelligence.tables.{$key}", $default);

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $b) use ($columns, $name): void {
            $b->index($columns, $name);
        });
    }

    private function dropIndex(string $key, string $default, string $name): void
    {
        $table = (string) config("price-intelligence.tables.{$key}", $default);

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $b) use ($name): void {
            $b->dropIndex($name);
        });
    }
};
