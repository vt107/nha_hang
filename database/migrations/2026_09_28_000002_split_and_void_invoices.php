<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tách hóa đơn: 1 phiên bàn có thể có nhiều hóa đơn; món ghi nhận thuộc hóa đơn nào.
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('table_session_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['table_session_id']);
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
            $table->unique('table_session_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['table_session_id']);
        });
    }
};
