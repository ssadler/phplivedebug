<?php

class PhpLiveDebugException extends Exception {}

class PhpLiveDebugClient {
    
    public static $t0 = null;
    public static $t = null;
    
    static function write($sock, $data) {
        for ($written = 0; $written < strlen($data); $written += $fwrite) {
            $fwrite = fwrite($sock, substr($data, $written));
            if ($fwrite === false)
                break;
        }
        return $written;
    }
    
    function send($method, $data, $meta) {
        $sock = @fsockopen('127.0.0.1', 34455, $errno, $errstr, 0.01);
        if (!$sock) return false;
        stream_set_timeout($sock, 150);
        $meta = json_encode($meta);
        $data = (string)$data;
        $out = sprintf("PLD:[\"%s\",%s,%s]\r\n%s", $method, strlen($data), $meta,
                       $data);
        $this->write($sock, $out);
        $protocol = fread($sock, 4);
        $this->assert($protocol == "PLD:", "bad response");
        $header = trim(fgets($sock));
        list($status, $len) = json_decode($header);
        $this->assert($status == 'ok', 'server error');
        $data = 0 < $len ? fread($sock, $len) : null;
        stream_socket_shutdown($sock, STREAM_SHUT_RDWR);
        fclose($sock);
        return $data;
    }
    
    static function dump($args) {
        $html_errors = ini_get('html_errors'); ini_set('html_errors', 'Off');
        ob_start();
        foreach ($args as $arg) {
            if (is_scalar($arg) || is_null($arg))
                var_dump($arg);
            else
                print_r($arg);
        }
        $out = ob_get_clean();
        if ("\n" != substr($out, -1))
            $out .= "\n";
        ini_set('html_errors', $html_errors);
        return $out;
    }
    
    function _echo($data, $stack_depth=1) {
        $meta = $this->get_meta($stack_depth+1);
        $this->send('echo', $data, $meta);
    }
    
    private function get_meta($stack_depth) {
        $meta = debug_backtrace();
        $meta = $meta[$stack_depth];
        unset($meta['object'], $meta['args']);
        return $meta;
    }
    function _interact($input, $stack_depth=1) {
        $meta = $this->get_meta($stack_depth+1);
        return $this->send('interact', $input, $meta);
    }

    static function assert($cond, $msg) {
        if (!$cond)
            throw new PhpLiveDebugException($msg);
    }
    
    public static function fatal_shutdown_handler() {
        $err = error_get_last();
        if ($err['type'] === E_ERROR) {
            $msg = sprintf("Fatal error: %s in %s on line %s\n", $err['message'], $err['file'], $err['line']);
            $pld = new self;
            $pld->_echo($msg);
        }
    }

    private function interact_code_dummy_func() {
        #INTERACT_CODE_START
        $__pld = new StdClass;
        $__pld->html_errors = ini_get('html_errors'); ini_set('html_errors', 'Off');
        $__pld->client = new PhpLiveDebugClient;
        $__pld->out = '';
        $__pld->err = null;
        while (false !== ($__pld->code = $__pld->client->_interact($__pld->out))) {
            if ('break;' == $__pld->code)
                break;
            ob_start();
            try {
                $__pld->ret = eval($__pld->code);
                $__pld->out = ob_get_clean();
                if (strlen($__pld->out) and "\n" != substr($__pld->out, -1))
                    $__pld->out .= "\n";
                if (null !== $__pld->ret)
                    $__pld->out .= PhpLiveDebugClient::dump(array($__pld->ret));
            } catch (Exception $e) {
                if (is_a($e, 'PhpLiveDebugException')) throw $e;
                $__pld->out = PhpLiveDebugClient::dump($e);
            }
        }
        restore_error_handler();
        ini_set('html_errors', $__pld->html_errors);
        #INTERACT_CODE_END
    }

    static function get_interact_code() {
        $this_file = file_get_contents(__FILE__);
        preg_match('/#INTERACT_CODE_START(.+?)#INTERACT_CODE_END/s',
                   file_get_contents(__FILE__), $match);
        PhpLiveDebugClient::assert($match, 'failed getting interact code');
        return trim($match[1]);
    }
}



function __() {
    $client = new PhpLiveDebugClient;
    $args = func_get_args();
    $data = PhpLiveDebugClient::dump($args);
    $client->_echo($data);
}

define('__', PhpLiveDebugClient::get_interact_code());

register_shutdown_function(array('PhpLiveDebugClient', 'fatal_shutdown_handler'));
PhpLiveDebugClient::$t0 = PhpLiveDebugClient::$t = microtime(1);
