<?php
use Phinx\Migration\AbstractMigration;

class CreateTQueueJob extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Status:
     *  0: waiting for processing
     *  1: process completed successfully
     *  2: process failed
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     *
     * Create tables with 'additional' data source:
     *      bin/cake migrations migrate --plugin CakeQueue -c "additional"
     *
     */
    public function change()
    {
        $table = $this->table('t_queue_job', ['id' => true,'primary_key' => ['id']]);
        $table
            ->addColumn('job_id', 'integer', [
                'null' => false
            ])
            ->addColumn('job_data', 'text', [
                'null' => false
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'length' => 1,
                'default'  => '0'
            ])
            ->addColumn('comment', 'string', [
                'null' => true,
                'default'  => NULL
            ])
            ->addColumn('created', 'datetime', [
                'null' => false
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false
            ])
            ->addIndex('job_id', ['type' => 'index','unique' => FALSE,'name' => 'job_id_index'])
            ->create();
    }
}
