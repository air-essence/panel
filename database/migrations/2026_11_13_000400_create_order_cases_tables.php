<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sprawy do zamówień. Na razie tylko anulacje, później zwroty i reklamacje w tej samej tabeli.
        Schema::create('order_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('anulacja')->index();
            $table->string('status', 20)->default('nowa')->index();
            $table->string('reason', 30);
            $table->string('reason_details')->nullable();
            $table->date('requested_on');
            $table->string('refund_status', 20);
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->date('refunded_on')->nullable();
            $table->string('shipment_hold', 20);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->json('changes')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_case_events');
        Schema::dropIfExists('order_cases');
    }
};
