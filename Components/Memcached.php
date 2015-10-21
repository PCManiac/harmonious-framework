<?php
// класс для работы с memcached-серверами
class Harmonious_Components_Memcached implements IHarmonious_Component{
        private $mc_servers;
       
        // инициализация
        public function __construct (Harmonious $app) {
            $this->app = $app;
            $this->mc_servers = $this->app->config('memcached_servers');
        }
       
        private function get_server($key) {           
            $mc_server = $this->mc_servers[crc32($key) % count($this->mc_servers)];
            $m = new Memcached();
            $m->setOption(Memcached::OPT_NO_BLOCK, true);
            $m->setOption(Memcached::OPT_COMPRESSION, false);
            
/*            foreach ($this->mc_servers as $mc_server) {
                $m->addServer($mc_server, 11211);
            }            */
            $m->addServer($mc_server, 11211);
            return $m;
        }
        
        // запись в memcached без блокировок
        function set($key, $value, $expiration = 3600/*1 час*/) {
                $m = $this->get_server($key);
                $ret = $m->set($key, $value, $expiration);
                if (!$ret) $retcode = $m->getResultCode();
                unset($m);
                if (!$ret) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));                    
                else return array('result'=>true );
        }
    
        // чтение из memcached
        //$usecas - будет ли использоваться Check And Set
        function get($key, $usecas = false) {
                $m = $this->get_server($key);
                if (!$usecas) $ret = $m->get($key);
                else $ret = $m->get($key, null, $cas);    
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                $ret = array ('result'=>true, 'value'=>$ret);
                if ($usecas) $ret['cas'] = $cas;
                return $ret;
        }
        
        // удаление из memcached
        function delete($key) {
                $m = $this->get_server($key);
                $ret = $m->delete($key);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array ('result'=>true);
        }
        
        //запись в memcached только в том случае, если данные не изменялись другим параллельным потоком.
        function cas($cas, $key, $value, $expiration = 3600/*1 час*/) {
                $m = $this->get_server($key);
                $ret = $m->cas($cas, $key, $value, $expiration);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array('result'=>true);
        }        
        
        // увеличение счетчика на смещение
        function inc($key, $offset = 1) {
                $m = $this->get_server($key);
                $ret = $m->increment($key, $offset);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array('result'=> true, 'value'=>$ret);
        }
        
        // уменьшение счетчика на смещение
        function dec($key, $offset = 1) {
                $m = $this->get_server($key);
                $ret = $m->decrement($key, $offset);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array('result'=> true, 'value'=>$ret);
        }
        
        // добавление строки в конец строки, хранящейся в ключе memcached
        function append($key, $value) {
                $m = $this->get_server($key);
                $ret = $m->append($key, $value);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array('result'=> true);
        }

        // добавление строки в начало строки, хранящейся в ключе memcached
        function prepend($key, $value) {
                $m = $this->get_server($key);
                $ret = $m->prepend($key, $value);
                if ($ret === false) $retcode = $m->getResultCode();
                unset($m);
                if ($ret === false) return array ('result'=>false, 'result_code'=>$retcode, 'result_string'=>$this->retcode2string($retcode));
                else return array('result'=> true);
        }     
        
        public function getStatus() {
            $m = new Memcached();
            $m->setOption(Memcached::OPT_NO_BLOCK, true);
            foreach ($this->mc_servers as $mc_server) $m->addServer($mc_server, 11211);
            return $m->getStats();
        }
        
        private function retcode2string ($retcode) {
            switch ($retcode) {
                case 0: return 'RES_SUCCESS';
                case 1: return 'RES_FAILURE';
                case 2: return 'RES_HOST_LOOKUP_FAILURE';
                case 7: return 'RES_UNKNOWN_READ_FAILURE';
                case 8: return 'RES_PROTOCOL_ERROR';
                case 9: return 'RES_CLIENT_ERROR';
                case 10: return 'RES_SERVER_ERROR';
                case 5: return 'RES_WRITE_FAILURE';
                case 12: return 'RES_DATA_EXISTS';
                case 14: return 'RES_NOTSTORED';
                case 16: return 'RES_NOTFOUND';
                case 18: return 'RES_PARTIAL_READ';
                case 19: return 'RES_SOME_ERRORS';
                case 20: return 'RES_NO_SERVERS';
                case 21: return 'RES_END';
                case 26: return 'RES_ERRNO';
                case 32: return 'RES_BUFFERED';
                case 31: return 'RES_TIMEOUT';
                case 33: return 'RES_BAD_KEY_PROVIDED';
                case 11: return 'RES_CONNECTION_SOCKET_CREATE_FAILURE';
                case -1001: return 'RES_PAYLOAD_FAILURE';
               
            }
        }
}

?>
