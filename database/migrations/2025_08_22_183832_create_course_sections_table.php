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
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("module_id");
            $table->string("title");
            $table->text("description")->nullable();
            $table->unsignedInteger("nr_of_files")->default(0);
            $table->unsignedInteger("duration")->comment("In Seconds")->default(0);
            $table->timestamps();

            $table->foreign("module_id")->references('id')->on("course_modules")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_sections');
    }
};
