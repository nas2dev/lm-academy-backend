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
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("course_section_id");
            $table->string("title");
            $table->enum("type", ["text", "image", "video", "file"]);
            $table->text("content")->nullable();
            $table->string("material_url", 1023)->nullable();
            $table->unsignedTinyInteger("sort_order");
            $table->unsignedBigInteger("created_by")->nullable();
            $table->unsignedBigInteger("updated_by")->nullable();
            $table->timestamps();

            $table->foreign("course_section_id")->references("id")->on("course_sections")->onDelete("cascade");
            $table->foreign("created_by")->references("id")->on("users")->onDelete("set null");
            $table->foreign("updated_by")->references("id")->on("users")->onDelete("set null");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
