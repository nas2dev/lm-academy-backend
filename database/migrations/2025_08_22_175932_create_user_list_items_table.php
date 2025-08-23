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
        Schema::create('user_list_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("list_id");
            $table->unsignedBigInteger("user_id");
            $table->timestamps();

            $table->unique(["list_id", "user_id"]);

            $table->foreign("list_id")->references("id")->on("user_lists")->onDelete("cascade");
            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_list_items');
    }
};
