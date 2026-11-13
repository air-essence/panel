<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reklamacje — zastępują arkusze „Reklamacje Medivon / Air Essence / Beautifly” i „Serwis urządzeń”.
        Schema::create('order_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('nowa')->index();
            $table->string('product_name');
            $table->string('ean', 20)->nullable()->index();
            $table->date('reported_on');
            $table->text('defect_description');
            $table->string('product_location', 30);
            $table->string('service_name', 100)->nullable();
            $table->date('service_sent_on')->nullable();
            $table->date('service_returned_on')->nullable();
            $table->date('responded_on')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->date('resolved_on')->nullable();
            $table->string('wz_number', 100)->nullable();
            $table->string('drive_link', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_complaint_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->json('changes')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_complaint_events');
        Schema::dropIfExists('order_complaints');
    }
};
