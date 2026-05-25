<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caches the embryonic snapshot captured during discovery (PROJECT.md §5.1 step 5) on the
 * proposal so the admin's side-by-side review screen can render the candidate (title, image,
 * price, host) without an extra fetch. All nullable: discovery may not have a full snapshot yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('price-intelligence.tables.match_proposals', 'pi_match_proposals');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'candidate_title')) {
                $blueprint->string('candidate_title')->nullable();
            }
            if (! Schema::hasColumn($table, 'candidate_image_url')) {
                $blueprint->text('candidate_image_url')->nullable();
            }
            if (! Schema::hasColumn($table, 'candidate_price_cents')) {
                $blueprint->unsignedBigInteger('candidate_price_cents')->nullable();
            }
            if (! Schema::hasColumn($table, 'candidate_host')) {
                $blueprint->string('candidate_host')->nullable();
            }
        });
    }

    public function down(): void
    {
        $table = (string) config('price-intelligence.tables.match_proposals', 'pi_match_proposals');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['candidate_title', 'candidate_image_url', 'candidate_price_cents', 'candidate_host'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
