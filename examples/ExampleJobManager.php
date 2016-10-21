<?php

/**
* An example JobManager wrapper class to extend custom functionality.
* Each JobManager extension class must have a constructor.
*/
class ExampleJobManager extends JobManager\JobManager {
    // The default path of your logfile
    const DEFAULT_LOGFILE = '/path/to/home/connexuslogs/joblog';
    
    /**
     * Constructor for our custom JobManager extension class.
     * 
     * @private
     */
    function __construct() {
        // We want to establish a database connection here so we can fetch and process jobs.
        // This method can be found in the JobManager class
        $this->connect('localhost', 'user', 'password', 'database');
        
        // We also call the JobManager constructor to set our logfile
        parent::__construct();
        $this->setLogPath(self::DEFAULT_LOGFILE);
    }
    
    /**
     * Custom implemented run function.
     * This is overriding the default run method in JobManager.
     * This allows you to have some fine-grained control on how you want to process jobs.
     */
    public function run() {
        $i = 0;
        
        // Run until we break
        while ($i < 100) {
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
