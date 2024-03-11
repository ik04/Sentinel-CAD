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
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean("is_default")->default(true);
            $table->boolean("is_default_img")->default(true);
            $table->integer("favourites_count")->default(0);
            $table->integer("followers_count")->default(0);
            $table->integer("friends_count")->default(0);
            $table->integer("average_posts")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            //
        });
    }
};
