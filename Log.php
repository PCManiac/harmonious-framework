<?php
/**
 * Log Adapter
 *
 * This is an adapter for your own custom Logger. This adapter assumes
 * your custom Logger provides the following public instance methods:
 *
 * debug( mixed $object )
 * info( mixed $object )
 * warn( mixed $object )
 * error( mixed $object )
 * fatal( mixed $object )
 *
 * This class assumes nothing else about your custom Logger, so you are free
 * to use Apache's Log4PHP logger or any other log class that, at the
 * very least, implements the five public instance methods shown above.
 
 * @since   Version 1.0
 */
class Harmonious_Log {

    /**
     * @var mixed An object that implements expected Logger interface
     */
    protected $loggers = array();

    /**
     * @var bool Enable logging?
     */
    protected $enabled;

    /**
     * Constructor
     */
    public function __construct() {
        $this->enabled = true;
    }

    /**
     * Enable or disable logging
     * @param   bool    $enabled
     * @return  void
     */
    public function setEnabled( $enabled ) {
        if ( $enabled ) {
            $this->enabled = true;
        } else {
            $this->enabled = false;
        }
    }

    /**
     * Is logging enabled?
     * @return bool
     */
    public function isEnabled() {
        return $this->enabled;
    }

    public function __call($name, $args) {
        if ($this->isEnabled()) {
            $name = strtolower($name);
            $object = $args[0];
            
            $result = true;
            foreach ($this->loggers as $logger) {
                switch ($name) {
                    case 'debug':
                        if (!$logger->debug($object)) $result = false;
                        break;
                    case 'info':
                        if (!$logger->info($object)) $result = false;
                        break;
                    case 'warn':
                        if (!$logger->warn($object)) $result = false;
                        break;
                    case 'error':
                        if (!$logger->error($object)) $result = false;
                        break;
                    case 'fatal':
                        if (!$logger->fatal($object)) $result = false;
                        break;
                    default:
                        return false;
                        break;
                }  
            }
            return $result;
        } else return false;
    }
    
    /**
     * Set Logger
     * @param   mixed   $logger
     * @return  void
     */
    public function setLogger( $logger ) {
        $this->loggers[] = $logger;
    }

    /**
     * Get Logger
     * @return mixed
     */
    public function getLoggers() {
        return $this->loggers;
    }

}