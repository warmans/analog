<?php

require_once ('../Analog.php');

class AnalogTest extends PHPUnit_Framework_TestCase {
    
    private $testLogLocation = 'analog.txt';
    
    private function _deleteLogIfExists(){
        if(file_exists($this->testLogLocation)){
            unlink($this->testLogLocation);
        }
    }
    
    public function setUp(){
        $this->_deleteLogIfExists();
        
        /*ensure class is reset each test*/
        Analog::set_format(NULL);
        Analog::set_location(NULL);
        
        parent::setUp();
    }
    
    public function tearDown(){
        $this->_deleteLogIfExists();
    }

    function test_LogMessageDefault(){
        $logMessage = new LogMessage('Test', Analog::ALERT, 'amachine');
                                
        $this->assertEquals(
            implode(' - ', $logMessage->get_elements())."\n",
            (string)$logMessage
        );
    }

    function test_LogMessageFormat(){
        $logMessage = new LogMessage('Test', Analog::ALERT, 'amachine');
        $format = '%s / %s / %d / %s\n';
        $formattedMessage = $logMessage->format($format);
        
        $args = $logMessage->get_elements();
        array_unshift($args, $format);
        
        $this->assertEquals(
            call_user_func_array('sprintf', $args),
            $formattedMessage
        );
    }

    function test_LogMessageGetter(){
        $logMessage = new LogMessage('Test', Analog::ALERT, 'amachine');
        $this->assertEquals(
            'amachine',
            $logMessage->machine
        );
    }

    function test_LogWriterStd(){

        $location = $this->testLogLocation;
        $format = '%s . %s . %d . %s\n';

        $logWriter = new LogWriter($location, $format);
        $strategy = $logWriter->get_strategy();

        $this->assertTrue(($strategy instanceof StdLog));
    }


    function test_LogWriterClosure(){

        $location = function($messageAsString){
            return TRUE;
        };

        $format = function($message){
            return $message->message. ' | '.$message->level;
        };

        $logWriter = new LogWriter($location, $format);
        $strategy = $logWriter->get_strategy();
                
        $this->assertTrue(($strategy instanceof ClosureLog));
    }


    function test_StdLog(){
        $location = $this->testLogLocation;
        $format = '%s | %s | %d | %s\n';

        $logMessage = new LogMessage('A Message', Analog::ALERT);

        $logWriter = new StdLog($location, $format);
        $logWriter->write_message($logMessage);

        //file was created
        $this->assertFileExists($location);           

        $args = $logMessage->get_elements();
        array_unshift($args, $format);
        
        //file contains what we think
        $this->assertEquals(
            call_user_func_array('sprintf', $args),
            file_get_contents($location)
        );

    }

    function test_StdClosure(){

        $logFileName = $this->testLogLocation;

        $format = function($message){
            return $message->message. ' | '.$message->level;
        };

        $location = function($messageAsString) use ($logFileName) {
            return file_put_contents($logFileName, $messageAsString);
        };

        $logMessage = new LogMessage('A Message', Analog::ALERT);

        $logWriter = new ClosureLog($location, $format);
        $logWriter->write_message($logMessage);

        //file was created
        $this->assertFileExists($logFileName);

        //file contains what we think
        $this->assertEquals(
            $format($logMessage),
            file_get_contents($logFileName)
        );
    }
    
    function test_Analog(){
        $location = $this->testLogLocation;
        $format = '%s | %s';

        Analog::set_format($format);
        Analog::set_location($location);
        
        Analog::log('Analog Logged', 1);

        //file was created
        $this->assertFileExists($location);           
        
        //file contains what we think
        $this->assertEquals(
            'Analog Logged | 1',
            file_get_contents($location)
        );
    }
    
    function test_AnalogDefaultLocation(){
        $defaultLocation = sys_get_temp_dir() . 'analog.txt';
        
        //setup
        if(file_exists($defaultLocation)){
            unlink($defaultLocation);    
        }
                
        Analog::log('Debug info', Analog::DEBUG);
                
        //file was created
        $this->assertFileExists($defaultLocation);           
        
        //tear down
        unlink($defaultLocation);
    }
}