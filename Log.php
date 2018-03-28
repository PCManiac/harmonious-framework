<?php
/**
 * Slim - a micro PHP 5 framework
 *
 * @author      Josh Lockhart <info@joshlockhart.com>
 * @copyright   2011 Josh Lockhart
 * @link        http://www.slimframework.com
 * @license     http://www.slimframework.com/license
 * @version     1.5.0
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

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
 *
 * @package Slim
 * @author  Josh Lockhart <info@joshlockhart.com>
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
            $result = true;
            foreach ($this->loggers as $logger) {
                switch ($name) {
                    case 'debug':
                        if (!$logger->debug($args)) $result = false;
                        break;
                    case 'info':
                        if (!$logger->info($args)) $result = false;
                        break;
                    case 'warn':
                        if (!$logger->warn($args)) $result = false;
                        break;
                    case 'error':
                        if (!$logger->error($args)) $result = false;
                        break;
                    case 'fatal':
                        if (!$logger->fatal($args)) $result = false;
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