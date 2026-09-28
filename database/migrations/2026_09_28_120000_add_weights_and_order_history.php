<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // What actually went out (2 kg ordered, 2,14 kg weighed). Null = as ordered.
            $table->decimal('delivered_quantity', 10, 3)->nullable()->after('quantity');
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained()->nullOnDelete();
            // Kept as written, so the history still reads right after a login is removed.
            $table->string('actor_name')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_quantity');
        });
    }
};
