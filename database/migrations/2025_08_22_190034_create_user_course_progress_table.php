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
        Schema::create('user_course_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id");
            $table->unsignedBigInteger("course_id");
            $table->unsignedTinyInteger("completed_sections")->default(0);
            $table->json("completed_section_ids")->nullable();
            $table->unsignedTinyInteger("pending_sections")->nullable();

            $table->unsignedTinyInteger("completed_modules")->default(0);
            $table->json("completed_module_ids")->nullable();
            $table->unsignedTinyInteger("pending_modules")->nullable();
            $table->boolean("awarded")->default(false);


            $table->timestamps();

            $table->unique(["user_id", "course_id"]);

            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade");
            $table->foreign("course_id")->references("id")->on("courses")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_course_progress');
    }
};
