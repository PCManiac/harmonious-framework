<?php
// класс для работы с redis-сервером
class Harmonious_Components_Redis implements IHarmonious_Component{
        private $redis;
       
        // инициализация
        public function __construct (Harmonious $app) {
            $this->app = $app;
            
            $redis_server_ip = $this->app->config('redis_server_ip');
            $redis_server_port = $this->app->config('redis_server_port');
            $redis_server_timeout = ($this->app->config('redis_server_timeout')!==null ? $this->app->config('redis_server_timeout') : 1);
            $redis_server_password = $this->app->config('redis_server_password');
            
            $this->redis = new Redis();
            $this->redis->connect($redis_server_ip, $redis_server_port, $redis_server_timeout);
            if (isset($redis_server_password)) $this->redis->auth($redis_server_password);
        }
        
        public function __destruct () {
            $this->redis->close();
        }
              
        function set($key, $value, $expiration = 3600/*1 час*/) {
                $ret = $this->redis->setEx($key, $expiration, $value);
                if (!$ret) return array ('result'=>false);
                else return array('result'=>true );
        }
    
        function get($key) {
                $ret = $this->redis->get($key);
                if ($ret === false) return array ('result'=>false);
                else $ret = array ('result'=>true, 'value'=>$ret);
                return $ret;
        }
        
        function delete($key) {
                $ret = $this->redis->delete($key);
                if ($ret === false) return array ('result'=>false);
                else return array ('result'=>true);
        }

        function append($key, $value) {
                $ret = $this->redis->append($key, $value);
                if ($ret == 0) return array ('result'=>false);
                else return array('result'=> true);
        }

        function exists($key) {
                $ret = $this->redis->exists($key);
                if ($ret == 0) return array ('result'=>false);
                else return array('result'=> true);
        }
}

?>
