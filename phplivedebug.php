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
            $client->sock = @fsockopen('127.0.0.1', 34455, $errno,
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
    
    function _echo($args, $stack_depth=3) {
        $data = self::dump($args);
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
        $__pld_o = '';
        while (true) {
            $__pld_code = $__pld->_interact($__pld_o);
            if ('break;' == $__pld_code)
                break;
            ob_start();
            $__pld_r = eval($__pld_code);
            $__pld_o = ob_get_clean();
            if (strlen($__pld_o) and "\n" != substr($__pld_o, -1))
                $__pld_o .= "\n";
            if (null !== $__pld_r)
                $__pld_o .= PhpLiveDebugClient::dump(array($__pld_r));
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

