<?php

    namespace JobManager;

    /**
    * This is the base class for a job, which is comprised of a constructor, preProcess, run, and postProcess.
    * This class helps you to abstract away database communications about the jobs current state.
    */
    class Job {
        // Default failed status
        const STATUS_FAILED = 'FAILED';

        // Default pending status
        const STATUS_PENDING = 'PENDING';

        // Default running status
        const STATUS_RUNNING = 'RUNNING';

        // Define the namespace for Jobs
        const CLASS_NAMESPACE = 'JobManager\Jobs\\';

        /**
         * Constructs a new Job object.
         * 
         * @private
         * 
         * @param int           $jobID
         * @param string        $class
         * @param array|object  $data
         */
        function __construct($jobID, $class, $data = null) {
            $this->job_id = $jobID;
            $this->job_class = self::CLASS_NAMESPACE.$class;
            $this->data = is_array($data) ? (object) $data : $data;
            $this->request_id = uniqid();

            // Default job to success true with no errors.
            $this->success = true;
            $this->error = '';
        }

        /**
         * Wrapper method for setting the job's database connection.
         * 
         * @param object $db
         */
        public function setDB($db) {
            $this->db = $db;
        }

        /**
         * Gets the job's id.
         * 
         * @return int
         */
        public function getJobID() {
            return $this->job_id;
        }

        /**
         * Gets the job's request id.
         * 
         * @return int
         */
        public function getRequestID() {
            return $this->request_id;
        }

        /**
         * Updates the status of the job in the database.
         * 
         * @param  string  $status
         * @return boolean
         */
        private function updateStatus($status) {
            $safeStatus = $this->db->real_escape_string($status);
            $query = "UPDATE unprocessed_jobs SET status='$safeStatus' WHERE job_id='$this->job_id'";

            $result = $this->db->query($query);

            if (!$result) {
                 return false;
            }

            $this->status = $safeStatus;
            return true;
        }

        /**
         * Deletes a job from the database.
         * 
         * @return boolean
         */
        private function delete() {
            $query = "DELETE FROM unprocessed_jobs WHERE job_id='$this->job_id'";

            $result = $this->db->query($query);

            if (!$result) {
                 return false;
            }

            return true;
        }

        /**
         * Simple wrapper to bake consistently structured return statements.
         *                     
         * @return object
         */
        private function bakeReturn() {
            $return = (object) [
                'success' => $this->success
            ];

            if ($this->error) {
                $return->error = $this->error;
            }

            if ($this->return_data) {
                $return->data = $this->return_data;
            }

            return $return;
        }

        /**
         * Runs before the main run command to process the job.
         * 
         * @return boolean
         */
        private function preProcess() {
            return $this->updateStatus(self::STATUS_RUNNING);
        }

        /**
        * Main running class for the job.
        * 
        * @return array
        */
        public function run() {
            // Run if we fail to execute the pre-process
            if (!$this->preProcess()) {
                $this->success = false;
                $this->error = 'Error with preProcess()';
                $this->updateStatus(self::STATUS_FAILED);
            }

            // Create a new intance of the job class to run
            $jobTask = new $this->job_class($this->data);

            // Run if we fail to execute the job's run method
            if (!$jobTask->run()) {
                $this->success = false;
                $this->error = 'Error with run()';
                $this->updateStatus(self::STATUS_FAILED);
            }

            // Run if we fail to execute the post-process
            if (!$this->postProcess()) {
                $this->success = false;
                $this->error = 'Error with postProcess()';
                $this->updateStatus(self::STATUS_FAILED);
            }

            return $this->bakeReturn();
        }

        /**
         * Runs directly following the run command on job completion.
         * 
         * @return boolean
         */
        private function postProcess() {        
            if (!$this->success) {
                return $this->updateStatus(self::STATUS_FAILED);
            } else {
                return $this->delete();
            }
        }
    }

?>