<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('setting_key', 25)->primary();
            $table->string('value', 25);
        });

        DB::table('settings')->insert([
            'setting_key' => 'imageScraper_lastId',
            'value' => 1,
        ]);

        DB::table('settings')->insert([
            'setting_key' => 'indexInsert_lastId',
            'value' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
