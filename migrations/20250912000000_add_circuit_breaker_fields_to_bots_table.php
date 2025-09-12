<?php

use Phinx\Migration\AbstractMigration;

class AddCircuitBreakerFieldsToBotsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('bots');
        $table->addColumn('failure_count', 'integer', ['default' => 0, 'null' => false, 'after' => 'token'])
              ->addColumn('circuit_breaker_open_until', 'integer', ['default' => 0, 'null' => false, 'after' => 'failure_count'])
              ->update();
    }
}
