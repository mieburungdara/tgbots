<?php

use Phpmig\Migration\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class AddGroupNameTofeatureChannels extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        Capsule::schema()->table('feature_channels', function (Blueprint $table) {
            $table->string('discussion_group_name')->nullable()->after('discussion_group_id');
        });
    }

    /**
     * Undo the migration
     */
    public function down()
    {
        Capsule::schema()->table('feature_channels', function (Blueprint $table) {
            $table->dropColumn('discussion_group_name');
        });
    }
}
