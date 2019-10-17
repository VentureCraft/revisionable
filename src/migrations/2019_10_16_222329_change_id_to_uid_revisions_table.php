<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class ChangeIdToUidRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('revisions', function ($table) {
            
            $column = 'uid';
            if(!Schema::hasColumn($table->getTable(), $column))
            {
                $table->uuid($column)->first();
                echo "  [Added column '".$column."' to ".$table->getTable().".]\n";
            }
        });
        Schema::table('revisions', function ($table) {
            $column = 'uid';
            if(Schema::hasColumn($table->getTable(), $column))
            {
                echo "  [adding uuid's]... (might take a while)\n";
                foreach (DB::table($table->getTable())->get('id') as $k => $v) {
                    DB::table($table->getTable())->where('id', '=', $v->id)->update(['uid' => Str::uuid()]);
                }
            }
            
            
            $column = 'id';
            if(Schema::hasColumn($table->getTable(), $column))
            {
                $table->integer($column)->unsigned()->change();
                $table->dropPrimary([$column]); //remove the primary key
                $table->dropColumn($column);
                echo "  [Dropped column '".$column."' from ".$table->getTable().".]\n";
            }
        });
        Schema::table('revisions', function ($table) {
            
            $column = 'uid';
            if(Schema::hasColumn($table->getTable(), $column))
            {
                $table->primary($column);
                echo "  [primary column '".$column."' added to ".$table->getTable().".]\n";
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
                echo "  [Dropped column '".$column."' from ".$table->getTable().".]\n";
            }
        });
        
        Schema::table('revisions', function ($table) {
            $column = 'id';
            if(!Schema::hasColumn($table->getTable(), $column))
            {
                $table->increments($column)->first();
                echo "  [Added column '".$column."' to ".$table->getTable().".]\n";
            }
        });
    }
}
