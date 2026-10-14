<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('profiles', 'profile_id')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('profile_id');
            });
        }

        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('profile_id')->nullable()->after('id');
        });

        foreach (DB::table('profiles')->select('id')->orderBy('id')->cursor() as $profile) {
            DB::table('profiles')->where('id', $profile->id)->update([
                'profile_id' => 'TFMB-'.$profile->id,
            ]);
        }

        Schema::table('profiles', function (Blueprint $table): void {
            $table->unique('profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropUnique(['profile_id']);
            $table->dropColumn('profile_id');
        });
    }
};
