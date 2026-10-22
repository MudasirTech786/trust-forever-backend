<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('gender', 20)->nullable()->change();
            $table->string('marital_status', 30)->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->unsignedTinyInteger('age')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('age');
        });
    }
};
