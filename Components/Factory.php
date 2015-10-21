<?php
    include "component.php";
    
    class Harmonious_Components_Factory implements IteratorAggregate, ArrayAccess, Countable {
        
        private $instances = array();
        private $app;
        
        public function __construct(Harmonious $app) {
            $this->app = $app;
        }
        
        public function getIterator() {
            return new ArrayIterator($this->instances);
        }

        public function offsetSet($key, $value) {
            if (isset($this->instances[$key])) throw new Exception('Component ' . $key . ' allready exists');            
            $this->instances[$key] = $value;
        }
        
        public function offsetUnset($key) {
            unset($this->instances[$key]);
        }

        public function offsetGet($key) {
            if ( !isset($this->instances[$key]) ) {
                $path = $this->app->config('components_path');
                if ( isset($path) and file_exists($path . '/' . strtolower($key) . '.php') ) {
                    include_once $path . '/' . strtolower($key) . '.php';
                    $classname = $key;
                    $this->instances[$key] = new $classname($this->app);
                } elseif ( file_exists(__DIR__ . '/' . ucfirst(strtolower($key)) . '.php') ) {
                    include_once ucfirst(strtolower($key)) . '.php';
                    $classname = 'Harmonious_Components_' . ucfirst(strtolower($key));
                    $this->instances[$key] = new $classname($this->app);
                } else {
                    throw new Exception('Component ' . $key . ' not exists');
                }
            }
            return $this->instances[$key];
        }
        
        public function offsetExists($key) {
            return isset($this->instances[$key]);
        }
        
        public function count() {
            return count($this->instances);
        }
    }
?>
