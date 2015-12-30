<?php
namespace CakeQueue\API;

use Cake\Controller\ComponentRegistry;
use Pheanstalk\Exception;
use Pheanstalk\Pheanstalk;
use Cake\ORM\TableRegistry;
use Cake\I18n\I18n;

class BeanstalkdAPI {

    protected $_beanstalkd;
    protected $_host;
    protected $_port;
    protected $_tube;

    function __construct($tube = 'default', $host = '127.0.0.1', $port = '11300'){
        $this->_host = $host;
        $this->_port = $port;
        $this->_tube = $tube;

        $this->_beanstalkd = new Pheanstalk($host, $port);
    }

    /**
     * See more on QueueMySqlAPI pushJobToMySql()
     * @param $id
     */
    public function pushJob($id){
        $this->_beanstalkd
            ->useTube($this->_tube)
            ->put($id);
    }

    public function listenJob(){
        $this->_beanstalkd->watch($this->_tube);

        echo "Listening jobs in tube: {$this->_tube}\n";
        while($job = $this->_beanstalkd->reserve()) {
            $this->executeJob($job);
            echo "\nListening other jobs in tube: {$this->_tube}\n";
        }
    }

    /**
     * @param \Pheanstalk\Job $job
     */
    public function executeJob($job){
        $job_id             = $job->getId();
        $t_queue_job_id     = $job->getData();

        echo "\n-------------------------------------------------------------------\n";
        echo "Receive job id:{$job_id} in row: {$t_queue_job_id}\n";

        $queue_jobs_table   = TableRegistry::get('CakeQueue.QueueJobs');
        $t_queue_job        = $queue_jobs_table->get($t_queue_job_id);
        $job_data           = json_decode($t_queue_job->job_data, TRUE);
        I18n::locale($t_queue_job->m_language_code);

        echo "Class: {$job_data['class_name']}\n";
        echo "Method: {$job_data['class_method']}\n";
        echo "Data:\n";
        var_export($job_data);

        echo "... processing ...\n";
        $processing_class = new $job_data['class_name'](new ComponentRegistry());
        $processing_method = $job_data['class_method'];

        $result = call_user_func_array([$processing_class, $processing_method],$job_data['data']);

        if(!$result){
            $msg  = "\n-----------------------JOB FAILED!-----------------------------\n";
            $msg .= "Time: " . date('Y-m-d H:i:s');
            $msg .= "Processing class: {$job_data['class_name']}\n";
            $msg .= "Processing method: {$job_data['class_method']}\n";
            $msg .= 'Data: ' . serialize($job_data['data']);
            $msg .= "\n---------------------------------------------------------------\n";
            error_log($msg, 3, "logs/beanstalk.log");

            $queue_jobs_table->patchEntity($t_queue_job, [
                'status'    =>  2,
                'modified'   => date('Y-m-d H:i:s'),
                'comment'   => $msg
            ]);
        } else {
            $queue_jobs_table->patchEntity($t_queue_job, [
                'job_id'        => $job_id,
                'status'        => 1,
                'modified'      => date('Y-m-d H:i:s')
            ]);
        }
        $queue_jobs_table->save($t_queue_job);

        echo "Process completed.\n";
        $this->_beanstalkd->delete($job);
        echo "Job {$job_id} is removed.\n";
        echo "-------------------------------------------------------------------\n\n";
    }


} 