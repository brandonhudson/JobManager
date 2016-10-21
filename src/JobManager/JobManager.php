<?php

namespace JobManager;

use JobManager\Job;

class JobManager {    
    /**
     * Constructs a new JobManager instance.
     * 
     * @private
     */
    function __construct() {
        $this->logging_enabled = false;
    }
    
    /**
     * Sets the logging path for the jobs logs.
     * 
     * @param string $logPath
     */
    public function setLogPath($logPath = 'logs') {
        $this->logger = new \Katzgrau\KLogger\Logger($logPath, \Psr\Log\LogLevel::DEBUG, array('filename' => 'joblog'));
        $this->loggin_enabled = true;
    }
    
    /**
     * Enables logging.
     */
    public function enableLogging() {
        $this->logging_enabled = true;
    }
    
    /**
     * Disables logging.
     */
    public function disableLogging() {
        $this->logging_enabled = false;
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
        $safeScheduled = $db->real_escape_string($scheduled);
        
        $query = "INSERT INTO unprocessed_jobs(class, data, status, created, scheduled) VALUES('$safeClass', '$safeData', '$safeStatus', $created, $scheduled)";
        
        if (!$this->db->query($query)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Logs an alert line in the job logs.
     * 
     * @param string $message
     */
    public function alert($message) {
        if (!$this->logging_enabled) {
            return;
        }
        
        $this->logger->alert($message);
    }
    
    /**
     * Logs a warning line in the job logs.
     * 
     * @param string $message
     */
    public function warn($message) {
        if (!$this->logging_enabled) {
            return;
        }
        
        $this->logger->warning($message);
    }
    
    /**
     * Logs an info line in the job logs.
     * 
     * @param string $message
     */
    public function info($message) {
        if (!$this->logging_enabled) {
            return;
        }
        
        $this->logger->info($message);
    }
    
    /**
     * Logs a debug line in the job logs.
     * 
     * @param string $message
     * @param array  $params
     */
    public function debug($message, $params = []) {
        if (!$this->logging_enabled) {
            return;
        }
        
        $this->logger->debug($message, $params);
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

            $this->info($job->getRequestID().' - running job');  
            $result = $job->run();
            
            // Log any errors we hit and run the next
            if (!$result || !$result['success']) {
                $this->alert($job->getRequestID().' - error running job');

                continue;
            }

            $this->info($job->getRequestID().' - successfully completed job');
        }
    }
}

?>