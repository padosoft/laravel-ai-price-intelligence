<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category-attribute extraction on content snapshots: `attributes_status` tells API clients
 * whether to wait (pending) or read latest_content.attributes; `attributes_signature` lets a
 * later scrape of an unchanged page reuse the extraction instead of paying the LLM again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.content_snapshots', 'pi_content_snapshots');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'attributes_status')) {
                $blueprint->string('attributes_status', 16)->nullable();
            }
            if (! Schema::hasColumn($table, 'attributes_signature')) {
                $blueprint->string('attributes_signature', 64)->nullable();
            }
            if (! Schema::hasColumn($table, 'attributes_extracted_at')) {
                $blueprint->timestamp('attributes_extracted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $table = (string) config('price-intelligence.tables.content_snapshots', 'pi_content_snapshots');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['attributes_status', 'attributes_signature', 'attributes_extracted_at'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
