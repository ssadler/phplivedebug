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
        $out = sprintf("PLD:[\"%s\",%s,%s]\n", $method, strlen($data), $meta);
        fwrite($this->sock, $out);
        fwrite($this->sock, $data);
        $protocol = fread($this->sock, 4);
        $this->assert("PLD:" == $protocol, 'bad response');
        $header = '';
        while (true) {
            $c = fread($this->sock, 1);
            if ("\n" == $c) break;
            $header .= $c;
        }
        list($status, $len) = json_decode($header);
        $this->assert($status == 'ok', 'server error');
        $data = (0 == (int)$len) ? null : fread($this->sock, $len);
        return $data;
    }
    
    function _echo($args, $stack_depth=1) {
        ob_start();
        foreach ($args as $arg) {
            if (is_scalar($arg) || is_null($arg)) {
                var_dump($arg);
            } else {
                print_r($arg);
            }
        }
        $data = ob_get_clean();
        $meta = debug_backtrace();
        $meta = $meta[$stack_depth];
        unset($meta['object'], $meta['args']);
        $this->send('echo', $data, $meta);
    }

    function _interact($input) {
        return $this->send('interact', $input, null);
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
            ob_start();
            eval($__pld->_interact($__pld_output));
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
    return $match[1];
}
define('__', __pld_get_interact_code());

