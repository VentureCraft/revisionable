<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddResponsibleInStudyReport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        echo "Modify (report_field) add (ref)..." . PHP_EOL;

        Schema::table('report_field', function($table)
        {
           $table->string('ref')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        echo "Modify (report_field) drop (ref)..." . PHP_EOL;

        if (Schema::hasColumn('report_field', 'ref'))
        {
            Schema::table('report_field', function($table)
            {
                $table->dropColumn('ref');
            });
        }
    }
}
