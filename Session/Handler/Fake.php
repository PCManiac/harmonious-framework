<?php
/**
 * Адаптация стандартного механизма работы с сессиями для Harmonious
 * Код взят отсюда: http://php.net/manual/ru/function.session-set-save-handler.php
 */

class Harmonious_Session_Handler_Fake extends Harmonious_Session_Handler {

    public function open( $savePath, $sessionName ) {
        return(true);
    }

    public function close() {
        return true; 
    }

    public function read( $id ) {
        return (string) "";
    }

    public function write( $id, $sessionData ) {
        return strlen($sessionData);
    }

    public function destroy( $id ) {
        return true;
    }

    public function gc( $maxLifetime ) {
        return true;
    }
  }
?>
