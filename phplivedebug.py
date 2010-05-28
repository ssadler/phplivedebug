#!/usr/bin/env python

import sys, errno, socket, json, php_input, swirl
from tornado import ioloop, iostream

last_file = None
last_line = None

class PLDServer:
    def __init__(self):
        self.sock = sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM, 0)
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        sock.setblocking(0)
        sock.bind(('127.0.0.1', 34455))
        sock.listen(5000)
        io_loop = ioloop.IOLoop.instance()
        io_loop.add_handler(sock.fileno(), self.serve_request, io_loop.READ)
        try:
            io_loop.start()
        except KeyboardInterrupt:
            io_loop.stop()
            print "exited cleanly"
    
    def serve_request(self, fd, events):
        while True:
            try:
                connection, address = self.sock.accept()
            except socket.error, e:
                if e[0] not in (errno.EWOULDBLOCK, errno.EAGAIN):
                    raise
                return
            connection.setblocking(0)
            stream = iostream.IOStream(connection)
            PLDHandler(stream)

class PLDHandler:
    def __init__(self, stream):
        self.stream = stream
        stream.read_until('\r\n', self._on_headers)
    
    def async_callback(func):
        def inner(self, *args, **kwargs):
            try:
                return func(self, *args, **kwargs)
            except:
                self.stream.close()
                raise
        return inner

    def print_caller(func):
        def inner(self, data, meta):
            global last_file, last_line
            file, line = meta.get('file'), meta.get('line')
            if file and line and (file, line) != (last_file, last_line):
                last_file = file
                last_line = line
                print colorise('From: %s:%s'%(file, line), 'yellow', True)
            return func(self, data, meta)
        return inner
    
    @async_callback
    def _on_headers(self, header):
        proto = header[:4]
        assert proto == 'PLD:', 'Bad protocol: ' + repr(proto)
        self.method, length, self.meta = json.loads(header[4:])
        self.stream.read_bytes(length, self._on_data)
    
    @async_callback
    def _on_data(self, data):
        out = getattr(self, '_'+self.method)(data, self.meta) or ''
        response = """PLD:["ok",%s]\r\n%s""" % (len(out), out)
        self.stream.write(response, self._on_written)
    
    @async_callback
    def _on_written(self):
        self.stream.close()

    @print_caller
    def _echo(self, data, meta):
        sys.stdout.write(data)
        sys.stdout.flush()

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
        PLDServer()
    except KeyboardInterrupt:
        pass

__name__ == "__main__" and main()
