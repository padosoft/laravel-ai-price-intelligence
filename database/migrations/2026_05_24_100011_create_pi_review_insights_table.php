<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.review_insights', 'pi_review_insights');

        if (Schema::hasTable($table)) {
            return;
        }

        // GDPR-safe: only anonymous aggregates are stored here — never raw review
        // text, author names, or any PII. See ReviewAggregator.
        Schema::create($table, function (Blueprint $b): void {
            $b->id();
            $b->unsignedBigInteger('tenant_id')->index();
            $b->unsignedBigInteger('competitor_product_id')->index();
            $b->string('period', 20);
            $b->float('sentiment_score');
            $b->json('themes')->nullable();
            $b->unsignedInteger('sample_count')->default(0);
            $b->boolean('is_ai_generated')->default(true);
            $b->timestamp('generated_at');
            $b->timestamps();

            $b->unique(['tenant_id', 'competitor_product_id', 'period'], 'pi_ri_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.review_insights', 'pi_review_insights'));
    }
};
