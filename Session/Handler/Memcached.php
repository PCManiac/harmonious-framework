<?php

class Harmonious_Session_Handler_Memcached extends Harmonious_Session_Handler {

    public function open( $savePath, $sessionName ) {
        return(true);
    }

    public function close() {
        return true; 
    }

    public function read( $id ) {
        $mc = $this->app->memcached;
        $key = "php_session_file_". $id;
        $ret = $mc->get($key);
        if(!$ret['result']) return "";
        else {
            $maxlifetime = ini_get('session.gc_maxlifetime');
            $mc->set($key, $ret['value'], $maxlifetime);
            return (string) $ret['value'];
        }
    }

    public function write( $id, $sessionData ) {
        $mc = $this->app->memcached;
        $key = "php_session_file_". $id;
        $maxlifetime = ini_get('session.gc_maxlifetime');
        $ret = $mc->set($key, $sessionData, $maxlifetime);
        if ($ret['result']) return true;
        else return(false);
    }

    public function destroy( $id ) {
        $mc = $this->app->memcached;
        $key = "php_session_file_". $id;
        $mc->delete($key);
        return(true);
    }

    public function gc( $maxLifetime ) {
        return true;
    }
  }
?>
