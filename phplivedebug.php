<?php

class PhpLiveDebugException extends Exception {}

class PhpLiveDebugClient {
    
    private $sock = null;
    public static $t = null;
    
    function connect() {
        $this->sock = @fsockopen('127.0.0.1', 34455, $errno, $errstr, 0.1);
        return (bool)$this->sock;
    }
    
    function __destruct() {
        if ($this->sock) {
            stream_socket_shutdown($this->sock, STREAM_SHUT_RDWR);
            fclose($this->sock);
            $this->sock = null;
        }
    }
    
    function write($data) {
        for ($written = 0; $written < strlen($data); $written += $fwrite) {
            $fwrite = fwrite($this->sock, substr($data, $written));
            if ($fwrite === false)
                break;
        }
        return $written;
    }
    
    function send($method, $data, $meta) {
        $meta = json_encode($meta);
        $data = (string)$data;
        $out = sprintf("PLD:[\"%s\",%s,%s]\n%s", $method, strlen($data), $meta,
                       $data);
        $this->write($out);
        $protocol = fread($this->sock, 4);
        $this->assert($protocol == "PLD:", "bad response");
        $header = fgets($this->sock);
        list($status, $len) = json_decode($header);
        $this->assert($status == 'ok', 'server error');
        $data = 0 < $len ? fread($this->sock, $len) : null;
        return $data;
    }
    
    static function dump($args) {
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
        return $out;
    }
    
    function _echo($args, $stack_depth=1) {
        $__pld->html_errors = ini_get('html_errors'); ini_set('html_errors', 'Off');
        $data = self::dump($args);
        $meta = $this->get_meta($stack_depth+1);
        $this->send('echo', $data, $meta);
        ini_set('html_errors', $__pld__html_errors);
    }
    
    private function get_meta($stack_depth) {
        $meta = debug_backtrace();
        $meta = $meta[$stack_depth];
        unset($meta['object'], $meta['args']);
        return $meta;
    }
    function _interact($input, $stack_depth=1) {
        stream_set_timeout($this->sock, 150); 
        $meta = $this->get_meta($stack_depth+1);
        return $this->send('interact', $input, $meta);
    }

    static function assert($cond, $msg) {
        if (!$cond)
            throw new PhpLiveDebugException($msg);
    }

    private function interact_code_dummy_func() {
        #INTERACT_CODE_START
        $__pld = new StdClass;
        $__pld->html_errors = ini_get('html_errors'); ini_set('html_errors', 'Off');
        $__pld->client = new PhpLiveDebugClient;
        $__pld->out = '';
        $__pld->err = null;
        while ($__pld->client->connect()) {
            $__pld->code = $__pld->client->_interact($__pld->out);
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
                $__pld_out = PhpLiveDebugClient::dump($e);
            }
        }
        unset($__pld->client);
        ini_set('html_errors', $__pld__html_errors);
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
    if ($client = PhpLiveDebugClient::get()) {
        $args = func_get_args();
        $client->_echo($args);   
    }
}

define('__', PhpLiveDebugClient::get_interact_code());

PhpLiveDebugClient::$t = microtime(1);
