<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_gateway');
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled, refunded
            $table->string('gateway_transaction_id')->nullable();
            $table->text('gateway_response')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('payment_method')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['payment_gateway', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_transactions');
    }
};
