<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $alerts = (string) config('price-intelligence.tables.alerts', 'pi_alerts');
        $webhooks = (string) config('price-intelligence.tables.webhook_subscriptions', 'pi_webhook_subscriptions');

        if (! Schema::hasTable($alerts)) {
            Schema::create($alerts, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->string('type', 40);
                $b->string('severity', 20)->default('info');
                $b->json('payload')->nullable();
                $b->unsignedBigInteger('product_id')->nullable();
                $b->unsignedBigInteger('competitor_product_id')->nullable();
                $b->json('channel_status')->nullable();
                $b->timestamp('acknowledged_at')->nullable();
                $b->timestamps();
                $b->index(['tenant_id', 'type'], 'pi_alerts_type_idx');
                $b->index(['tenant_id', 'acknowledged_at'], 'pi_alerts_ack_idx');
            });
        }

        if (! Schema::hasTable($webhooks)) {
            Schema::create($webhooks, function (Blueprint $b): void {
                $b->id();
                $b->unsignedBigInteger('tenant_id')->index();
                $b->text('url');
                $b->json('events')->nullable();
                $b->text('secret_encrypted')->nullable();
                $b->boolean('active')->default(true);
                $b->unsignedSmallInteger('last_status')->nullable();
                $b->timestamp('last_at')->nullable();
                $b->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('price-intelligence.tables.alerts', 'pi_alerts'));
        Schema::dropIfExists((string) config('price-intelligence.tables.webhook_subscriptions', 'pi_webhook_subscriptions'));
    }
};
