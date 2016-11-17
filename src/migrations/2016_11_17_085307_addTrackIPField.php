<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTrackIPField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        echo "Modify (revisions) add (ip)..." . PHP_EOL;

        Schema::table('revisions', function($table)
        {
           $table->string('ip', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        echo "Modify (revisions) drop (ip)..." . PHP_EOL;

        if (Schema::hasColumn('revisions', 'ip'))
        {
            Schema::table('revisions', function($table)
            {
                $table->dropColumn('ip');
            });
        }
    }
}
