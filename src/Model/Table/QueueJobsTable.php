<?php
/**
 * Created by PhpStorm.
 * User: tanbt
 * Date: 18/06/2015
 * Time: 16:16
 */

namespace CakeQueue\Model\Table;

use Cake\ORM\Table;

class QueueJobsTable extends Table {

    public static function defaultConnectionName() {
        return 'additional';
    }

    public function initialize(array $config)
    {
        $this->table('t_queue_job');
    }

}