<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rules = (string) config('price-intelligence.tables.repricing_rules', 'pi_repricing_rules');
        $decisions = (string) config('price-intelligence.tables.rule_decisions', 'pi_rule_decisions');

        if (! Schema::hasTable($rules)) {
            Schema::create($rules, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->string('name');
                $b->json('target_filter')->nullable();
                $b->string('strategy', 30);
                $b->json('parameters')->nullable();
                $b->unsignedSmallInteger('priority')->default(100);
                $b->string('status', 20)->default('active');
                $b->timestamps();
                $b->index(['tenant_id', 'status'], 'pi_rules_status_idx');
            });
        }

        if (! Schema::hasTable($decisions)) {
            Schema::create($decisions, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->unsignedBigInteger('repricing_rule_id')->index();
                $b->unsignedBigInteger('product_id')->index();
                $b->unsignedBigInteger('current_price_cents')->nullable();
                $b->unsignedBigInteger('suggested_price_cents')->nullable();
                $b->boolean('applied')->default(false);
                $b->string('reason')->nullable();
                $b->json('evidence')->nullable();
                $b->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.repricing_rules', 'pi_repricing_rules'));
        Schema::dropIfExists((string) config('price-intelligence.tables.rule_decisions', 'pi_rule_decisions'));
    }
};
