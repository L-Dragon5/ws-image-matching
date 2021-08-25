<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCardsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function($table) {
            $table->string('jp_name', 255)->nullable();
            $table->text('jp_body')->nullable();
            $table->string('jp_trait_1', 20)->nullable();
            $table->string('jp_trait_2', 20)->nullable();
            $table->string('jp_trait_3', 20)->nullable();
            $table->string('jp_trait_4', 20)->nullable();
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
            $table->dropColumn('jp_name');
            $table->dropColumn('jp_body');
            $table->dropColumn('jp_trait_1');
            $table->dropColumn('jp_trait_2');
            $table->dropColumn('jp_trait_3');
            $table->dropColumn('jp_trait_4');
        });
    }
}
