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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->text("description");
            $table->string("intro_video_url", 1023)->nullable();
            $table->string("intro_image_url")->nullable();
            $table->tinyInteger("status")->default(0);
            $table->unsignedInteger("nr_of_files")->default(0);
            $table->unsignedInteger("duration")->comment("In Seconds")->default(0);
            $table->unsignedBigInteger("created_by")->nullable();
            $table->unsignedBigInteger("updated_by")->nullable();
            $table->timestamps();

            $table->index("status");

            $table->foreign("created_by")->references("id")->on("users")->onDelete("set null");
            $table->foreign("updated_by")->references("id")->on("users")->onDelete("set null");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
