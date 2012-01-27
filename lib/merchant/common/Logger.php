<?php
/**
 * Contains merchant logger class
 * 
 * @package Aktive-Merchant
 */

/**
 * ANSI sequence to reset colors
 * @var string
 */
define('RESET_SEQ', "\033[0m");

/**
 * ANSI sequence to activate a color
 * @var string
 */
define('COLOR_SEQ', "\033[");

/**
 * ANSI sequence to begin bold
 * @var string
 */
define('BOLD_SEQ', "\033[1m");

/**
 * Description of Logger
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Logger
{

    public static $start_time;
    public static $path = null;
    public static $filename = 'development.log';
    public static $log4php = null;
    public static $response_file = null;
    public static $request_file = null;
    
    static public function log4php($log4php) {
        self::$log4php = $log4php;
    }
    
    static public function request_file($request_file) {
        self::$request_file = $request_file;
    }
    
    static public function response_file($response_file) {
        self::$response_file = $response_file;
    }
    
    static public function start_logging()
    {
        if (isset(self::$log4php)) {
            self::$log4php->debug("Merchant_Logger started");
            return;
        }
        self::$start_time = microtime(true);
        self::log(COLOR_SEQ . "1;32m"
            . "Started at : [" . date('H:i:s d-m-Y', time()) . "]"
            . RESET_SEQ);
    }

    private static function _pad_exception($exception){
        $trace_string = "\n";
        $trace = "\n".print_r($exception->getTraceAsString(), true)."\n";
        
        $pieces = explode("\n", $trace);
        
        unset($pieces[0]);              // Empty line
        unset($pieces[count($pieces)]); // Empty last line
        
        foreach ($pieces as $piece) {
            $trace_string .= '    '.$piece."\n"; // Pad 4 spaces
        }
        return $trace_string;
    }
    
    static public function log($string)
    {
        if (self::$log4php) {
            self::$log4php->debug($string);
            return;
        }
        
        $trace_string = '';
        if ($string instanceof Exception) {
            $trace_string = $this->_pad_exception($string);
        }
        
        if (null === self::$path) {
            self::$path = dirname(__FILE__) . '/../../../log/';
        }
        
        if (!is_writable(self::$path)) {
            $exception = new Exception('AktiveMerchant - Cannot write to path '.  realpath(self::$path));
            error_log($exception->getMessage()."\n".self::_pad_exception($exception));
            return;
        }
        
        $fp = fopen(self::$path . self::$filename, 'a');
        fwrite($fp, $string . "\n");
        fclose($fp);
    }

    static public function error_log($string)
    {
        if (self::$log4php) {
          self::$log4php->error($string);
          return;
        }
      
        self::log(COLOR_SEQ . "1;31m" . $string . RESET_SEQ);
    }

    static public function end_logging()
    {
        if (self::$log4php) {
          self::$log4php->debug("Merchant_Logger ended");
          return;
        }
        $buffer = COLOR_SEQ . "1;32mParse time: ("
            . number_format((microtime(true) - self::$start_time) * 1000, '4')
            . "ms)" . RESET_SEQ;
        self::log($buffer);
    }

    static public function save_response($string)
    {
        if (self::$response_file === FALSE) return;
        if (null === self::$path)
            self::$path = dirname(__FILE__) . '/../../../log/';
        if (self::$response_file === NULL) self::$response_file = 'response.xml';
        if (substr(self::$response_file,1,1) == '=') {
          self::$response_file = $self::$path . '/' . self::$response_file;
        }
        
        if (!is_writable(self::$response_file) OR !file_exists(self::$response_file)) {
            return;
        }

        $fp = fopen($response_file, 'w');
        fwrite($fp, $string);
        fclose($fp);
    }

    static public function save_request($string)
    {
        if (self::$request_file === FALSE) return;
        if (null === self::$path) self::$path = dirname(__FILE__) . '/../../../log/';
        if (self::$request_file === NULL) self::$request_file = 'response.xml';
        if (substr(self::$request_file,1,1) == '=') {
          self::$request_file = $self::$path . '/' . self::$request_file;
        }

        if (null === self::$path)
            self::$path = dirname(__FILE__) . '/../../../log/';

        if (!is_writable(self::$request_file) OR !file_exists(self::$request_file)) {
            return;
        }

        $fp = fopen(self::$request_file, 'w');
        fwrite($fp, $string);
        fclose($fp);
    }

    static public function print_ar($array)
    {
        echo '<pre>' . "\n";
        print_r($array);
        echo '</pre>' . "\n";
    }

}

?>
