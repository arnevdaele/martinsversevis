<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // Used for every customer whose type and record name no schedule.
            $table->boolean('is_default')->default(false);
            // ISO weekday (1 = Monday) => {enabled, cutoff_days, cutoff_time}. See DeliverySchedule::days().
            $table->jsonb('days');
            // How far ahead a customer may pick a date.
            $table->unsignedSmallInteger('horizon_days')->default(21);
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->timestamps();
        });

        // Closures and extra delivery days, for every schedule at once.
        Schema::create('delivery_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10); // closed | extra
            $table->date('starts_on');
            $table->date('ends_on');
            // Extra days only: until when customers can still order for it.
            $table->dateTime('cutoff_at')->nullable();
            $table->string('reason')->nullable();
            $table->jsonb('translations')->nullable();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('customer_types', function (Blueprint $table) {
            $table->foreignId('delivery_schedule_id')->nullable()->after('notification_emails')->constrained()->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('delivery_schedule_id')->nullable()->after('customer_type_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_schedule_id'));
        Schema::table('customer_types', fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_schedule_id'));
        Schema::dropIfExists('delivery_exceptions');
        Schema::dropIfExists('delivery_schedules');
    }
};
