<?php
    abstract class Harmonious_Controller 
    {
        public function supportsHttpMethod($method) {
            return false;
        }
        
        abstract public function run($app);
    }
?>
