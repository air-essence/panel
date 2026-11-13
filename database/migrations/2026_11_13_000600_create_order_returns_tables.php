<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zwroty (odstąpienia od umowy, zwroty częściowe, rabaty) — zastępują arkusze „Zwroty $$$” per spółka.
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('nowa')->index();
            $table->string('kind', 20);
            $table->date('requested_on');
            $table->string('goods_status', 20);
            $table->date('received_on')->nullable();
            $table->string('goods_location', 100)->nullable();
            $table->string('return_tracking_number', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('refund_method', 20);
            $table->string('refund_account', 50)->nullable();
            $table->date('refunded_on')->nullable();
            $table->string('sale_document', 20);
            $table->string('sale_document_number', 100);
            $table->string('correction_document_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_return_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->json('changes')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_events');
        Schema::dropIfExists('order_returns');
    }
};
