<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('contact_name', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 20)->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 60);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('default_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('unit', 20)->default('each');
            // Whole units throughout; quantities are never fractional.
            $table->integer('current_stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->bigInteger('unit_cost_amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            // Set when the item drops to its reorder point; cleared once it is above it again.
            $table->timestamp('low_stock_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'is_active', 'name']);
            $table->index(['organization_id', 'category_id']);
        });

        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'inventory_location_id']);
            $table->index(['organization_id', 'inventory_location_id']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            // A stocked item, or a one-off described in words.
            $table->foreignUuid('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name', 160);
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('deliver_to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost_amount');
            $table->bigInteger('total_amount');
            $table->string('currency', 3);
            $table->date('needed_by')->nullable();
            $table->text('reason')->nullable();
            $table->string('supplier_reference', 80)->nullable();
            $table->unsignedInteger('received_quantity')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Stops a double submit from raising two requests.
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['organization_id', 'requester_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->foreignUuid('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // Always positive; direction comes from the type and the locations.
            $table->unsignedInteger('quantity');
            $table->foreignUuid('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->integer('stock_after');
            $table->bigInteger('unit_cost_amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('reference', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['inventory_item_id', 'occurred_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table inventory_movements add constraint inventory_movements_type_check check (type in ('receipt', 'issue', 'adjustment', 'transfer'))");
            DB::statement('alter table inventory_movements add constraint inventory_movements_quantity_check check (quantity > 0)');
            DB::statement('alter table inventory_stock_levels add constraint inventory_stock_levels_quantity_check check (quantity >= 0)');
            DB::statement('alter table inventory_items add constraint inventory_items_stock_check check (current_stock >= 0)');
            DB::statement("alter table purchase_requests add constraint purchase_requests_status_check check (status in ('submitted', 'approved', 'rejected', 'ordered', 'received', 'cancelled'))");
            DB::statement('alter table purchase_requests add constraint purchase_requests_amounts_check check (quantity > 0 and unit_cost_amount >= 0 and total_amount >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_categories');
    }
};
