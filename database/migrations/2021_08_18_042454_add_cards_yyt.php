<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCardsYyt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function($table) {
            $table->string('yyt_price', 10)->nullable();
            $table->string('yyt_set_code')->nullable();
            $table->timestamp('yyt_last_updated')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function($table) {
            $table->dropColumn('yyt_price');
            $table->dropColumn('yyt_set_code');
            $table->dropColumn('yyt_last_updated');
        });
    }
}
