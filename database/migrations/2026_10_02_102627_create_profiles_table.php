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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_picture')->nullable();
            $table->string('name');
            $table->string('gender', 20)->index();
            $table->string('marital_status', 30)->index();
            $table->date('date_of_birth')->index();
            $table->string('height', 40)->nullable();
            $table->string('sect')->nullable()->index();
            $table->string('caste')->nullable()->index();
            $table->string('language')->nullable();
            $table->string('qualification')->nullable();
            $table->string('educational_institution')->nullable();
            $table->boolean('employed')->nullable();
            $table->text('father_details')->nullable();
            $table->text('mother_details')->nullable();
            $table->string('house_size')->nullable();
            $table->string('society')->nullable();
            $table->string('city')->nullable()->index();
            $table->unsignedSmallInteger('sisters_count')->default(0);
            $table->text('sisters_details')->nullable();
            $table->unsignedSmallInteger('married_sisters_count')->default(0);
            $table->unsignedSmallInteger('brothers_count')->default(0);
            $table->text('brothers_details')->nullable();
            $table->unsignedSmallInteger('married_brothers_count')->default(0);
            $table->text('partner_requirements')->nullable();
            $table->string('father_whatsapp', 30)->nullable();
            $table->string('mother_whatsapp', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
