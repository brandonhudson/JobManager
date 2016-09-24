<?php

    // By default, we namespace all jobs in the JobManager\Jobs namespace.
    // If you want to change this, you will also need to modify the CLASS_NAMESPACE constant in the Job class
    namespace JobManager\Jobs;

    /**
    * This is an example job to give you an idea of how to structure a job.
    * Each job must have a constructor and a run method.
    */
    class ExampleJob {
        
        /**
         * Constructs a new ExampleJob task.
         * The constructor always takes a data parameter, which is the data fetched from the db by the JobManager.
         * 
         * @param array  $data
         * @param object $db
         */
        function __construct($data) {
            if (empty($data) || !$data->message) {
                return false;
            }
            
            $this->message = $data->message;
        }
        
        /**
         * Custom run method for the job.
         * It is up to you to define the core logic of your job here.
         * 
         * @return boolean
         */
        public function run() {
            if ($this->shouldEchoMessage()) {
                echo $this->message;
            }
            
            // If we return true, we signify that the method has successfully completed
            // Returning false with signify that we failed
            return true;
        }
        
        /**
         * This is an example of how you can incorporate custom methods into your job.
         * 
         * @return boolean
         */
        private function shouldEchoMessage() {
            if (strlen($this->message) > 8) {
                return true;
            }
            
            return false;
        }
            
    }

?>