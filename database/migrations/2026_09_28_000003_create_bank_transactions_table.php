<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Giao dịch ngân hàng nhận qua webhook (SePay). provider + provider_id unique để webhook gửi lại không bị ghi 2 lần.
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('provider_id', 64);
            $table->string('account_number', 30)->nullable();
            $table->unsignedBigInteger('amount');
            $table->text('content')->nullable();
            $table->string('reference_code', 100)->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->foreignId('table_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('confirmed_by')->nullable()->change();
            $table->foreignId('bank_transaction_id')->nullable()->after('reference')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_transaction_id');
        });
        Schema::dropIfExists('bank_transactions');
    }
};
