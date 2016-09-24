<?php

namespace JobManager;

use JobManager\Job;

abstract class JobManager {
    
    // Define preset log levels
    const ALERT = 'alert';
    const WARN = 'warn';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    const DEFAULT_LOG_FILE = 'joblog';
    
    function __construct($logfile = null) {
        $this->logfile = $logfile ?: self::DEFAULT_LOG_FILE;
    }
    
    /**
     * Opens a new db connection with the provided credentials.
     * 
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $database
     */
    public function connect($host = 'localhost', $user = '', $password = '', $database = '') {
        $this->db = $this->db($host, $user, $password, $database);
    }
    
    /**
     * Returns a new instance of an auth database object or false if it could not connect.
     * 
     * @return boolean/object
     */
    private function db($host, $user, $password, $database) {
        $db = new \mysqli($host, $user, $password, $database);

        if (!$db || $db->connect_error) {
            return false;
        }

        return $db;
    }

    /**
     * Fetches a new job.
     * 
     * @return Job
     */
    protected function fetch() {
        $time = time();
        $requestID = uniqid();
        $query = "SELECT * FROM unprocessed_jobs WHERE scheduled<=$time AND status='PENDING' LIMIT 1";

        $this->log(self::INFO, 'fetching new job', $requestID);

        $result = $this->db->query($query);

        if (!$result) {
            $this->log(self::ALERT, 'error fetching job', $requestID, ['error', $db->error]);
            return false;
        }

        if (!$result->num_rows) {
            //TODO: Log error that we could not get job here
            $this->log(self::INFO, 'no jobs to fetch', $requestID);
            return false;  
        }

        $data = $result->fetch_assoc();

        $job = new Job($data['job_id'], $data['class'], json_decode($data['data']));
        $job->setDB($this->db);
        
        $this->log(self::INFO, 'successfully fetched job.', $job->request_id, ['jobID' => $job->job_id]);
        
        return $job;
    }
    
    /**
     * Creates a new job in the database.
     * 
     * @param string $class  this is the PHP class that will be executed
     * @param array  $data   any data needed for the job
     * @param string $status the current job status
     *                                       
     * @return bool
     */
    public function create($class, $data = [], $status = 'PENDING', $scheduled = null) {
        $safeClass = $this->db->real_escape_string($class);
        $safeData = $this->db->real_escape_string(json_encode($data));
        $safeStatus = $this->db->real_escape_string($status);
        $created = time();
        $scheduled = $scheduled ?: time();
        
        $query = "INSERT INTO unprocessed_jobs(class, data, status, created, scheduled) VALUES('$safeClass', '$safeData', '$safeStatus', $created, $scheduled)";
        
        if (!$this->db->query($query)) {
            return false;
        }
        
        return true;
    }

    /**
     * Logs to the server.
     * 
     * @param string $level
     * @param string $message
     * @param string $uid
     * @param array  $params
     */
    public function log($level,$message, $uid = 'XXXXXXXXXXXXX', $params = []) {
        date_default_timezone_set('Etc/UTC');
        
        $log = ''.date(DATE_ATOM).'  '.$uid.'  '.$level.'  '.$message;
        
        foreach($params as $key=>$value){
            $log = $log . '  ['.$key.' = '.$value.']';
        }
        $log = $log . "\n";
        
        file_put_contents($this->logfile, $log, FILE_APPEND);
    }
    
    /**
    * Base function for running a custom JobManager instance.
    */
    public function run() {
        // Run until we break
        while (true) {
            $i++;
            $job = $this->fetch();
            
            // Break if there are no jobs to run
            if (!$job) {
                break;
            }
            
            $this->log(parent::INFO, 'running job.', $job->getRequestID(), ['jobID' => $job->getJobID()]);
            
            $result = $job->run();
            
            // Log any errors we hit and run the next
            if (!$result || !$result['success']) {
                $this->log(
                    parent::ALERT, 'error running job.', 
                   $job->getRequestID(), 
                   ['jobID' => $job->getJobID(), 'error' => $result['error']]
                );
                
                continue;
            }
            
            $this->log(parent::INFO, 'successfully completed job.', $job->getRequestID(), ['jobID' => $job->getJobID()]);
        }
    }
}

?>