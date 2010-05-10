#!/usr/bin/env python

import sys, json, SocketServer, php_input, select

class PLDServer(SocketServer.TCPServer):
    allow_reuse_address = True

class PLDHandler(SocketServer.BaseRequestHandler):
    last_file = None
    last_line = None
    
    def handle(self):
        # read request
        header, c = '', ''
        while True:
            c = self.request.recv(1)
            if c == "\n":
                break
            header += c
        bytes = header[:4]
        assert bytes == "PLD:", 'Bad protocol: ' + repr(bytes)
        method, length, meta = json.loads(header[4:])
        data = ''
        while length > 0:
            chunk = self.request.recv(1024)
            length -= len(chunk)
            data += chunk
        
        # respond
        out = getattr(self, '_'+method)(data, meta) or ''
        response = """PLD:["ok",%s]\n%s""" % (len(out), out)
        self.request.sendall(response)
        
    def print_caller(func):
        def inner(self, data, meta):
            file, line = meta['file'], meta['line']
            if (file, line) != (self.last_file, self.last_line):
                PLDHandler.last_file = file
                PLDHandler.last_line = line
                print colorise('From: %s:%s'%(file, line), 'yellow', True)
            return func(self, data, meta)
        return inner
    
    @print_caller
    def _echo(self, data, meta):
        sys.stdout.write(data)

    @print_caller
    def _interact(self, data, meta):
        self._echo(data, meta)
        return php_input.get_code()

termcolors = {
    'black': '0;30',
    'cyan': '36',
    'purple': '35',
    'h_white': '47',
    'h_red': '41',
    'h_green': '42',
    'yellow': '33',
    'red': '31',
}

def colorise(data, color, bold=False):
    code = ('1;' if bold else '') + termcolors[color]
    return "\033[%sm%s\033[1;m" % (code, data)

def main():
    try:
        import readline
    except ImportError:
        pass
    php_input.setup()
    try:
        PLDServer(('127.0.0.1', 34455), PLDHandler).serve_forever()
    except KeyboardInterrupt:
        pass

__name__ == "__main__" and main()
