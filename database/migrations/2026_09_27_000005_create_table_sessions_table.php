<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Một lượt khách ngồi bàn: mở khi quét QR (hoặc nhân viên mở), đóng khi thanh toán xong.
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->char('token', 32)->unique()->comment('Định danh công khai cho khách: kênh realtime, cookie');
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('qr');
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // Mỗi bàn chỉ có tối đa 1 phiên chưa đóng: cột sinh = dining_table_id khi chưa đóng, NULL khi đã đóng.
            $table->unsignedBigInteger('open_table_id')
                ->nullable()
                ->storedAs('IF(closed_at IS NULL, dining_table_id, NULL)')
                ->unique();

            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
