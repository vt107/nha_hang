<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1 phiên bàn : 1 hóa đơn. Doanh thu = tổng invoices.total có status paid, tính theo paid_at.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('service_charge_amount')->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
        });

        // Tiền mặt / chuyển khoản, nhân viên xác nhận tay: mỗi dòng là 1 khoản đã nhận.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('received_amount')->nullable()->comment('Tiền mặt khách đưa, để tính tiền thối');
            $table->string('reference')->nullable()->comment('Mã giao dịch chuyển khoản');
            $table->string('note')->nullable();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->index(['method', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
