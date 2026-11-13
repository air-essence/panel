<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baselinker_order_id')->unique();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_status_id')->nullable()->constrained()->nullOnDelete();
            // Surowe identyfikatory z BaseLinkera: po przypisaniu kanału lub statusu w panelu
            // zamówienia łączą się z nimi automatycznie.
            $table->string('baselinker_source', 100)->nullable()->index();
            $table->unsignedBigInteger('baselinker_status_id')->nullable()->index();
            $table->string('shop_order_id')->nullable();
            $table->string('external_order_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('delivery_method')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->boolean('is_cod')->default(false);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->timestamp('ordered_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('ean')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
        });

        Schema::create('baselinker_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('type', 50);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('orders_created')->default(0);
            $table->unsignedInteger('orders_updated')->default(0);
            $table->text('error')->nullable();
            $table->index(['source', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('baselinker_sources');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
