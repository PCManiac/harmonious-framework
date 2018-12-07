<?php
// класс для работы с redis-сервером
class Harmonious_Components_Redis implements IHarmonious_Component{
        private $redis;
       
        // инициализация
        public function __construct (Harmonious $app) {
            $this->app = $app;
            $this->redis = new Redis();
            $this->doConnect();
        }
        
        public function __destruct () {
            $this->redis->close();
        }
        
        protected function doConnect() {
            $redis_server_ip = $this->app->config('redis_server_ip');
            $redis_server_port = $this->app->config('redis_server_port');
            $redis_server_timeout = ($this->app->config('redis_server_timeout')!==null ? $this->app->config('redis_server_timeout') : 1);
            $redis_server_password = $this->app->config('redis_server_password');            
            
            $ret = $this->redis->connect($redis_server_ip, $redis_server_port, $redis_server_timeout);
            if (!$ret) return false;
            if (isset($redis_server_password)) $ret = $this->redis->auth($redis_server_password);
            if (!$ret) return false;
            return true;
        }
                
        function set($key, $value, $expiration = 3600/*1 час*/) {
                if (!$this->redis->isConnected()) {
                    if (!$this->doConnect()) return array ('result'=>false);
                }
                $ret = $this->redis->setEx($key, $expiration, $value);
                if (!$ret) return array ('result'=>false);
                else return array('result'=>true );
        }
    
        function get($key) {
                if (!$this->redis->isConnected()) {
                    if (!$this->doConnect()) return array ('result'=>false);
                }            
                $ret = $this->redis->get($key);
                if ($ret === false) return array ('result'=>false);
                else $ret = array ('result'=>true, 'value'=>$ret);
                return $ret;
        }
        
        function delete($key) {
                if (!$this->redis->isConnected()) {
                    if (!$this->doConnect()) return array ('result'=>false);
                }
                $ret = $this->redis->delete($key);
                if ($ret === false) return array ('result'=>false);
                else return array ('result'=>true);
        }

        function append($key, $value) {
                if (!$this->redis->isConnected()) {
                    if (!$this->doConnect()) return array ('result'=>false);
                }
                $ret = $this->redis->append($key, $value);
                if ($ret == 0) return array ('result'=>false);
                else return array('result'=> true);
        }

        function exists($key) {
                if (!$this->redis->isConnected()) {
                    if (!$this->doConnect()) return array ('result'=>false);
                }
                $ret = $this->redis->exists($key);
                if ($ret == 0) return array ('result'=>false);
                else return array('result'=> true);
        }
}

?>
