<?php

function __() {
    $client = PhpLiveDebugClient::get();
    if (!$client)
        return;
    $args = func_get_args();
    $client->_echo($args);
}

class PhpLiveDebugException extends Exception {}

class PhpLiveDebugClient {
    
    private $sock = null;
    
    function get() {
        $client = new self;
        if (!$client->sock)
            $client->sock = $sock = @fsockopen('127.0.0.1', 34455, $errno,
                                               $errstr, 0.1);
        return $client->sock ? $client : null;
    }
    
    function send($method, $data, $meta) {
        $meta = json_encode($meta);
        $data = (string)$data;
        $out = sprintf("PLD:[\"%s\",%s,%s]\n%s", $method, strlen($data), $meta,
                       $data);
        fwrite($this->sock, $out);
        $protocol = fread($this->sock, 4);
        $this->assert($protocol == "PLD:", "bad response");
        $header = fgets($this->sock);
        list($status, $len) = json_decode($header);
        $this->assert($status == 'ok', 'server error');
        $data = 0 < $len ? fread($this->sock, $len) : null;
        return $data;
    }
    
    function _echo($args, $stack_depth=3) {
        ob_start();
        foreach ($args as $arg) {
            if (is_scalar($arg) || is_null($arg))
                var_dump($arg);
            else
                print_r($arg);
        }
        $data = ob_get_clean();
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
}

function __interact() {
    #INTERACT_CODE_START
    if ($__pld = PhpLiveDebugClient::get()) {
        $__pld_output = '';
        while (true) {
            $__pld_code = $__pld->_interact($__pld_output);
            if (':quit' == $__pld_code)
                break;
            ob_start();
            echo eval($__pld_code);
            $__pld_output = ob_get_clean();
        }
    }
    #INTERACT_CODE_END
}

function __pld_get_interact_code() {
    $this_file = file_get_contents(__FILE__);
    preg_match('/#INTERACT_CODE_START(.+?)#INTERACT_CODE_END/s',
               file_get_contents(__FILE__), $match);
    PhpLiveDebugClient::assert($match, 'failed getting interact code');
    return trim($match[1]);
}
define('__', __pld_get_interact_code());

