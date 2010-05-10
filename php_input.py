import re
from subprocess import Popen, CalledProcessError, PIPE, check_call

class PhpSyntaxError(SyntaxError):
    pass

def setup():
    global have_php
    try:
        have_php = not check_call(["php", "-v"], stdin=PIPE,
                                  stdout=PIPE, stderr=PIPE)
    except OSError, CalledProcessError:
        have_php = False

def get_code():
    while True:
        try:
            return prepare(*prompt_user())
        except PhpSyntaxError as e:
            print e
        except KeyboardInterrupt:
            print "^C"
        except EOFError:
            print "^D"
            return 'break;'

def prompt_user():
    lines = []
    multiline = False
    while True:
        ps = '...  ' if multiline else 'php> '
        code = raw_input(ps).rstrip()
        if code:
            lines.append(code)
            if not multiline:
                if code[-1] == '{':
                    multiline = True
                    continue
                break
            elif code == '}':
                break
    return '\n'.join(lines), multiline

# re.search(r'(?<!\\)(["\'])(.*?)(?<!\\)(\1)', '"mlkfdfdsfdsfdsfsd"')

def prepare(code, multiline):
    if code and not code[-1] == ';':
        code += ';'
    if not have_php:
        return code
    test_code(code)
    if not multiline:
        if not re.match('\$[^\s]+ *=[^=]', code):
            try:
                code2 = 'return ' + code
                test_code(code2)
                return code2
            except PhpSyntaxError as e:
                pass
    return code

def test_code(code):
    php = Popen(['php', '-l'], stdin=PIPE, stdout=PIPE, stderr=PIPE)
    out, err = php.communicate('<?php ' + code)
    if 0 != php.returncode:
        raise PhpSyntaxError(format_error(out))

def format_error(err):
    lines = err.strip().splitlines()
    return '\n'.join(lines[:-1])
