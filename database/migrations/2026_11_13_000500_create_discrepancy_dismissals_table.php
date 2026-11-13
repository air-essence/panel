<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rozbieżności oznaczone przez pracownika jako wyjaśnione (z obowiązkowym wyjaśnieniem).
        Schema::create('discrepancy_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('rule', 50);
            $table->string('note', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'rule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discrepancy_dismissals');
    }
};
