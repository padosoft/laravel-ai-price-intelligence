<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $narratives = (string) config('price-intelligence.tables.narratives', 'pi_narratives');
        $assortment = (string) config('price-intelligence.tables.assortment_gaps', 'pi_assortment_gaps');
        $contentGaps = (string) config('price-intelligence.tables.content_gaps', 'pi_content_gaps');

        if (! Schema::hasTable($narratives)) {
            Schema::create($narratives, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->string('period', 20)->comment('ISO week, e.g. 2026-W21');
                $b->text('summary_md');
                $b->json('highlights')->nullable();
                $b->boolean('is_ai_generated')->default(true);
                $b->string('model_version', 50)->nullable();
                $b->timestamp('generated_at');
                $b->timestamps();
                $b->unique(['tenant_id', 'period'], 'pi_narr_tenant_period_uq');
            });
        }

        if (! Schema::hasTable($assortment)) {
            Schema::create($assortment, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->unsignedBigInteger('competitor_source_id')->nullable()->index();
                $b->string('category_path', 255);
                $b->string('competitor_product_url', 2048)->nullable();
                $b->string('title', 255)->nullable();
                $b->unsignedSmallInteger('importance_score')->default(50);
                $b->string('status', 20)->default('open');
                $b->boolean('is_ai_generated')->default(true);
                $b->timestamps();
                $b->index(['tenant_id', 'status'], 'pi_assort_status_idx');
            });
        }

        if (! Schema::hasTable($contentGaps)) {
            Schema::create($contentGaps, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->unsignedBigInteger('product_id')->index();
                $b->smallInteger('seo_score_delta')->default(0);
                $b->json('missing_attributes')->nullable();
                $b->json('title_recommendations')->nullable();
                $b->json('description_recommendations')->nullable();
                $b->unsignedSmallInteger('image_count_gap')->default(0);
                $b->boolean('is_ai_generated')->default(true);
                $b->timestamp('generated_at');
                $b->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.narratives', 'pi_narratives'));
        Schema::dropIfExists((string) config('price-intelligence.tables.assortment_gaps', 'pi_assortment_gaps'));
        Schema::dropIfExists((string) config('price-intelligence.tables.content_gaps', 'pi_content_gaps'));
    }
};
