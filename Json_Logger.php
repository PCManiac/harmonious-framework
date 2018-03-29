<?php
require 'Logger.php';

/**
 * Json_Logger
 *
 * @since   Version 1.0
 */
class Harmonious_Json_Logger extends Harmonious_Logger {
    protected $ignore_simple_texts = false;

    /**
     * Constructor
     * @param   string  $directory  Absolute or relative path to log directory
     * @param   int     $level      The maximum log level reported by this Logger
     */
    public function __construct( $app ) {
        $this->app = $app;
        $directory = $app->config('json_log.path');
        $level = $app->config('json_log.level');        
        $this->ignore_simple_texts = $app->config('json_log.ignore_simple_objects');
        $this->setDirectory($directory);
        $this->setLevel($level);
    }

    /**
     * Log data to file
     * @param   mixed               $data
     * @param   int                 $level
     * @return  void
     * @throws  RuntimeException    If log directory not found or not writable
     */
    protected function log( $data, $level ) {
        $dir = $this->getDirectory();
        if ( $dir == false || !is_dir($dir) ) {
            throw new RuntimeException("Log directory '$dir' invalid.");
        }
        if ( !is_writable($dir) ) {
            throw new RuntimeException("Log directory '$dir' not writable.");
        }
        
        if (is_array($data)) {
            $object = $data;
        } elseif (is_object($data)) {
            $object = (array)$data;
        } else {
            if ($this->ignore_simple_texts) return;
            $object = array();
            $object['text'] = (string)$data;
        }
        $object['error_level_int'] = $level;
        $object['error_level'] = $this->levels[$level];    
        $object['time'] = date('c');
        
        if ( $level <= $this->getLevel() ) {
            $this->write(json_encode($object)."\r\n");
        }
    }

}