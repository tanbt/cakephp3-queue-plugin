<?php
namespace CakeQueue\API;

use Pheanstalk\Exception;
use Cake\ORM\TableRegistry;
use Cake\I18n\I18n;
use CakeQueue\API\BeanstalkdAPI;
use Cake\Log\Log;

class QueueMySqlAPI {

    /**
     * Prepared item as a array data of a job
     * This API require iconic_intl_additional data source with table t_beanstalkd_job (next version)
     * currently, this version just writes log for jobs
     *
     * @param string $class_name
     * Full path of a class, including namespace
     * @param $class_method
     * Method of the class which will process the job
     * @param $send_to
     *  0: null         1: user     2: iconic
     *
     * @param array $data
     * Parameters for the method
     * Example:
     *      [
     *          'key1' => 'value1',
     *          'key2' => 'value2'
     *      ]
     *
     * @throws NotFoundException
     * @return int
     *      FALSE: class and/ore method are not existed
     *      job id
     *
     * All data put into queue must be array, do not use object (because of using json_encode)
     * @reference
     * http://stackoverflow.com/questions/804045/preferred-method-to-store-php-arrays-json-encode-vs-serialize
     */

    public function pushJobToMySql($class_name, $class_method, $send_to = 0, $data = []){
        if(method_exists($class_name, $class_method)){
            $job_data = [
                'class_name'    =>  $class_name,
                'class_method'  =>  $class_method,
                'data'          =>  $data,
            ];

            $queue_jobs_table = TableRegistry::get('CakeQueue.QueueJobs');
            $queue_job = $queue_jobs_table->newEntity([
                'm_language_code'   =>  I18n::locale(),
                'send_to'       => $send_to,
                'job_data'      => json_encode($job_data),
                'status'        => 0,
                'created'       => date('Y-m-d H:i:s'),
                'modified'      => date('Y-m-d H:i:s')
            ]);

            if($queue_jobs_table->save($queue_job)) {
                $beanstalkd_queue = new BeanstalkdAPI();
                $beanstalkd_queue->pushJob($queue_job->id);
            } else {
                Log::write('queue', 'Cannot save job to t_queue_job');
            }
        }
        return FALSE;
    }

} 