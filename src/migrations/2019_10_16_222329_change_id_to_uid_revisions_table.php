<?php

use Illuminate\Database\Migrations\Migration;

class CreateRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('revisions', function ($table) {
            $column = 'id';
            if(Schema::hasColumn($table->getTable(), $column))
            {
                $table->dropPrimary([$column]); //remove the primary key
                $table->dropColumn($column);
                echo "  [Dropped column '".$column."' from ".$table->getTable().".\n";
            }
            
            $column = 'uid';
            if(!Schema::hasColumn($table->getTable(), $column))
            {
                $table->uuid($column)->first();
                $table->primary($column);
                echo "  [Added column '".$column."' to ".$table->getTable().".\n";
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('revisions', function ($table) {
            $column = 'uid';
            if(Schema::hasColumn($table->getTable(), $column))
            {
                $table->dropPrimary([$column]); //remove the primary key
                $table->dropColumn($column);
                echo "  [Dropped column '".$column."' from ".$table->getTable().".\n";
            }
        
            $column = 'id';
            if(!Schema::hasColumn($table->getTable(), $column))
            {
                $table->increments($column)->first();
                echo "  [Added column '".$column."' to ".$table->getTable().".\n";
            }
        });
    }
}
