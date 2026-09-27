<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nhóm tùy chọn dùng chung cho nhiều món (vd "Size ly" cho mọi đồ uống).
        // min_select >= 1: bắt buộc chọn; max_select = 1: chọn một (radio).
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('internal_name')->nullable()->comment('Tên phân biệt trong admin, vd "Size - đồ uống"');
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price_delta')->default(0)->comment('Tiền cộng thêm vào giá món');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_option_group', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['menu_item_id', 'option_group_id']);
        });

        // Snapshot tùy chọn lúc gọi: [{group, name, price_delta}]. unit_price đã gồm tiền tùy chọn.
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('options'));
        Schema::dropIfExists('menu_item_option_group');
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
    }
};
