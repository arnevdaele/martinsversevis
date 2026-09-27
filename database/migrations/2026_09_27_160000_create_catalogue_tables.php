<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // System types (business, private) can be renamed but never deleted.
            $table->boolean('is_system')->default(false);
            // Extra addresses that receive every order from customers of this type.
            $table->jsonb('notification_emails')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Staff scoping: a user linked to types only sees those customers and
        // their orders. No rows means no restriction.
        Schema::create('customer_type_user', function (Blueprint $table) {
            $table->foreignId('customer_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_type_id', 'user_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('vat_number', 40)->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('BE');
            // Language for the portal, invitations and order mails; each login may override it.
            $table->string('locale', 5)->default('nl');
            $table->text('delivery_instructions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Portal logins. One customer (a restaurant, a shop) can have several people ordering.
        Schema::create('customer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            // Null until the invitation has been accepted.
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('receives_order_confirmations')->default(true);
            // Null = the customer's language.
            $table->string('locale', 5)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->jsonb('translations')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The catalogue is shared by every price list — and later by the public website.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 60)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('origin')->nullable();
            $table->string('unit', 20)->default('kg');
            $table->decimal('vat_rate', 5, 2)->default(6);
            $table->string('image')->nullable();
            $table->jsonb('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->jsonb('translations')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_type_price_list', function (Blueprint $table) {
            $table->foreignId('customer_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_type_id', 'price_list_id']);
        });

        // Lists granted to one customer on top of what their type sees.
        Schema::create('customer_price_list', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'price_list_id']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null = day price: orderable, priced when the order is confirmed.
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('min_quantity', 10, 3)->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('translations')->nullable();
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->date('requested_delivery_date')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('has_unpriced_items')->default(false);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        // Items snapshot name, unit and price: editing the catalogue never rewrites an order.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 60)->nullable();
            $table->string('unit', 20);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('quantity', 10, 3);
            $table->decimal('line_total', 12, 2)->nullable();
            $table->string('note')->nullable();
            // The product name in the other languages at the time of ordering.
            $table->jsonb('translations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'order_items', 'orders', 'price_list_items', 'customer_price_list', 'customer_type_price_list',
            'price_lists', 'products', 'product_categories', 'customer_password_reset_tokens',
            'customer_users', 'customers', 'customer_type_user', 'customer_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
