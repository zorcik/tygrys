<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('details');
            $table->string('image_url')->nullable()->after('image_path');
            $table->string('book_url')->nullable()->after('image_url');
            $table->text('description')->nullable()->after('book_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_url', 'book_url', 'description']);
        });
    }
};
