<?php
/**
 * Адаптация стандартного механизма работы с сессиями для Harmonious
 * Код взят отсюда: http://php.net/manual/ru/function.session-set-save-handler.php
 */

class Harmonious_Session_Handler_Files extends Harmonious_Session_Handler {

    public function open( $savePath, $sessionName ) {
        global $sess_save_path;

        $sess_save_path = $savePath;
        return(true);
    }

    public function close() {
        return true; 
    }

    public function read( $id ) {
        global $sess_save_path;

        $sess_file = "$sess_save_path/sess_$id";
        return (string) @file_get_contents($sess_file);
    }

    public function write( $id, $sessionData ) {
        global $sess_save_path;

        $sess_file = "$sess_save_path/sess_$id";
        if ($fp = @fopen($sess_file, "w")) {
        $return = fwrite($fp, $sessionData);
        fclose($fp);
        return $return;
        } else {
        return(false);
        }
    }

    public function destroy( $id ) {
        global $sess_save_path;

        $sess_file = "$sess_save_path/sess_$id";
        return(@unlink($sess_file));
    }

    public function gc( $maxLifetime ) {
        global $sess_save_path;

        foreach (glob("$sess_save_path/sess_*") as $filename) {
        if (filemtime($filename) + $maxlifetime < time()) {
          @unlink($filename);
        }
        }
        return true;
    }
  }
?>
