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
     * Prepared item as a array data of a job
     * This API require iconic_intl_additional data source with table t_beanstalkd_job (next version)
     * currently, this version just writes log for jobs
     *
     * @param string $class_name
     * Full path of a class, including namespace
     * @param $class_method
     * Method of the class which will process the job
     *
     * @param array $data
     * Parameters for the method
     * Example:
     *      [
     *          'key1' => 'value1',
     *          'key2' => 'value2'
     *      ]
     *
     * @return int
     *      FALSE: class and/ore method are not existed
     *      job id
     *
     * All data put into queue must be array, do not use object (because of using json_encode)
     * @reference
     * http://stackoverflow.com/questions/804045/preferred-method-to-store-php-arrays-json-encode-vs-serialize
     */
    public function pushJob($class_name, $class_method, $data = []){
        if(method_exists($class_name, $class_method)){
            $job_data = [
                'class_name'    =>  $class_name,
                'class_method'  =>  $class_method,
                'data'          =>  $data
            ];

            $queue_jobs_table = TableRegistry::get('CakeQueue.QueueJobs');
            $queue_job = $queue_jobs_table->newEntity([
                'm_language_code'   =>  I18n::locale(),
                'job_data'      =>  json_encode($job_data),
                'status'        =>  0,
                'created'       => date('Y-m-d H:i:s'),
                'modified'      => date('Y-m-d H:i:s')
            ]);

            if($queue_jobs_table->save($queue_job)) {
                $this->_beanstalkd
                    ->useTube($this->_tube)
                    ->put($queue_job->id);
                return $queue_job->id;
            }
        }
        return FALSE;
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