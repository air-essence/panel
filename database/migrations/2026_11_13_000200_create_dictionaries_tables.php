<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type', 20);
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            // Identyfikator źródła zamówień w BaseLinkerze (uzupełniany po podłączeniu API).
            $table->string('baselinker_source', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('group', 40);
            // Identyfikator statusu w BaseLinkerze (uzupełniany po podłączeniu API).
            $table->unsignedBigInteger('baselinker_status_id')->nullable()->unique();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
        Schema::dropIfExists('sales_channels');
        Schema::dropIfExists('companies');
    }
};
