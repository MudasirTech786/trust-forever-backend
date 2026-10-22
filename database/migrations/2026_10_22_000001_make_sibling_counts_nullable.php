<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sisters_count')->nullable()->default(null)->change();
            $table->unsignedSmallInteger('married_sisters_count')->nullable()->default(null)->change();
            $table->unsignedSmallInteger('brothers_count')->nullable()->default(null)->change();
            $table->unsignedSmallInteger('married_brothers_count')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sisters_count')->default(0)->nullable(false)->change();
            $table->unsignedSmallInteger('married_sisters_count')->default(0)->nullable(false)->change();
            $table->unsignedSmallInteger('brothers_count')->default(0)->nullable(false)->change();
            $table->unsignedSmallInteger('married_brothers_count')->default(0)->nullable(false)->change();
        });
    }
};
