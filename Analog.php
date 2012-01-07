<?php
/**
 *  
 * Refactored version of original Analog class implementing a strategy pattern for 
 * logging in different ways (e.g. using closure or standard).
 * 
 * Comments may not longer be accurate.
 * 
 * Stefan Warman
 * 
 */

/**
 * Analog - PHP 5.3+ logging class
 *
 * Copyright (c) 2012 Johnny Broadway
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * A short and simple logging class for based on the idea of using closures for
 * configurability and extensibility. Functions as a static class, but you can
 * completely control the formatting and writing of log messages through closures.
 *
 * By default, this class will write to a file named /tmp/log.txt using a format
 * "machine - date - level - message\n".
 *
 * I wrote this because I wanted something simple and small like KLogger, and
 * preferably not torn out of a wider framework if possible. After searching,
 * I wasn't happy with the single-purpose libraries I found. With KLogger for
 * example, I didn't want an object instance but rather a static class, and I
 * wanted more flexibility in the back-end.
 *
 * I also found that the ones that had really flexible back-ends supported a lot
 * that I could never personally foresee needing, and could be easier to extend
 * with new back-ends that may be needed over time. Closures seem a natural fit for
 * this kind of thing.
 *
 * What about Analog, the logfile analyzer? Well, since it hasn't been updated
 * since 2004, I think it's safe to call a single-file PHP logging class the
 * same thing without it being considered stepping on toes :)
 *
 * Usage:
 *
 *     <?php
 *     
 *     require_once ('Analog.php');
 *     
 *     // Default logging to /tmp/log.txt
 *     Analog::log ('Log this error', Analog::ERROR);
 *     
 *     // Create a custom object format
 *     Analog::set_format (function ($message) {
 *       return (object) array (
 *             'machine' => $message->machine,
 *             'date'    => $message->date,
 *             'level'   => $message->level,
 *             'message' => $message->message
 *         );
 *     });
 *     
 *     // Log to a MongoDB log collection
 *     Analog::set_location (function ($message) {
 *         static $conn = null;
 *         if (! $conn) {
 *             $conn = new Mongo ('localhost:27017');
 *         }
 *         $conn->mydb->log->insert ($message);
 *     });
 *     
 *     // Log an error
 *     Analog::log ('The sky is falling!');
 *     
 *     // Log some debug info
 *     Analog::log ('Debugging info', Analog::DEBUG);
 *     
 *     ?>
 *
 * @package Analog
 * @author Johnny Broadway
 */
class Analog {
    /**
     * List of severity levels.
     */
    const URGENT = 0; // It's an emergency
    const ALERT = 1; // Immediate action required
    const CRITICAL = 2; // Critical conditions
    const ERROR = 3; // An error occurred
    const WARNING = 4; // Something unexpected happening
    const NOTICE = 5; // Something worth noting
    const INFO = 6; // Information, not an error
    const DEBUG = 7; // Debugging messages

    /**
     * The default format for log messages (machine, date, level, message).
     */
    private static $format = NULL;

    /**
     * The location to save the log output. See Analog::location()
     * for details on setting this.
     */
    private static $location = NULL;

    /**
     * Format getter/setter. Usage:
     *
     *     Analog::set_format ("%s, %s, %d, %s\n");
     *
     * Using a closure:
     *
     *     Analog::set_format (function ($message) {
     *          return sprintf (
     *              "%s [%d] %s\n", 
     *              $message->date, 
     *              $message->level, 
     *              $message->message
     *          );
     *     });
     */
    public static function set_format($format) {
        self::$format = $format;
    }

    /**
     * Location getter/setter. Usage:
     *
     *    Analog::set_location ('my_log.txt');
     *
     * Using a closure:
     *
     *     Analog::set_location (function ($msg) {
     *         return error_log ($msg);
     *     });
     */
    public static function set_location($location) {
        self::$location = $location;
    }


    /**
     * This is the main function you will call to log messages.
     * Defaults to severity level Analog::ERROR.
     * Usage:
     *
     *     Analog::log ('Debug info', Analog::DEBUG);
     */
    public static function log($message, $level = 3) {
                
        //determine the strategy for writing the log
        $writer = new LogWriter(self::$location, self::$format);
        
        //create a new message
        $message = new LogMessage($message, $level);
        
        //write it
        return $writer->write_message($message);
    }

}

class LogMessage {

    private $elements = array();

    public function __construct($message, $level, $machine=NULL) {
        $this->elements['message'] = $message;
        $this->elements['level'] = $level;
        $this->elements['machine'] = $this->get_machine_name($machine);
        $this->elements['date'] = date('Y-m-d H:i:s');
    }

    private function get_machine_name($machine) {
        if ($machine) {
            return $machine;
        } else {
            return (isset($_SERVER['SERVER_ADDR'])) ? $_SERVER['SERVER_ADDR'] : 'localhost';
        }
    }

    public function get_elements() {
        return $this->elements;
    }
    
    public function __get($name) {
        return (!empty($this->elements[$name])) ? $this->elements[$name] : FALSE;
    }

    public function format($format) {
        $elements = $this->elements;
        array_unshift($elements, $format);
        
        return call_user_func_array('sprintf', $elements);
    }

    public function __toString() {
        return implode(" - ", $this->elements) . "\n";
    }

}

class LogWriter {

    private $strategy;

    public function __construct($location, $format=NULL) {
        switch (TRUE):
            case ($location instanceof Closure):
                $this->strategy = new ClosureLog($location, $format);
                break;
            default:
                $this->strategy = new StdLog($location, $format);
                break;
        endswitch;
    }
    
    public function get_strategy(){
        return $this->strategy;
    }

    public function write_message($message) {
        return $this->strategy->write_message($message);
    }

}

interface LogStrategyInterface {

    function write_message(LogMessage $message);
}

class ClosureLog implements LogStrategyInterface {

    private $format;
    private $location;

    public function __construct(Closure $location, $format=NULL) {
        $this->format = $format;
        $this->location = $location;
    }

    public function write_message(LogMessage $message) {

        $formatter = $this->format;
        $location = $this->location;

        $formattedMessage = ($this->format instanceof Closure) ? $formatter($message) : $message;

        return $location($formattedMessage);
    }

}

class StdLog implements LogStrategyInterface {

    private $format;
    private $location;

    public function __construct($location=NULL, $format=NULL) {
        $this->location = $location;
        $this->format = $format;
    }

    public function write_message(LogMessage $message) {
        
        //ensure valid default
        $location = ($this->location) ?: sys_get_temp_dir() . 'analog.txt';
                
        $f = fopen($location, 'a+');
        if (!$f) {
            throw new LogicException('Could not open file for writing');
        }

        if (!flock($f, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Could not lock file');
        }

        $formattedMessage = ($this->format) ? $message->format($this->format) : (string) $message;

        fwrite($f, $message->format($formattedMessage));
        flock($f, LOCK_UN);
        fclose($f);
        return true;
    }

}