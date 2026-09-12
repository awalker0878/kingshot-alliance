"""Loopback-only TLS fixture for the production webhook CurlHandler contract."""
import http.server
import json
import ssl
import sys


class Handler(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        body = self.rfile.read(int(self.headers.get('Content-Length', '0')))
        with open(sys.argv[3], 'a', encoding='utf-8') as log:
            log.write(json.dumps({'path': self.path, 'body': body.decode(), 'headers': dict(self.headers)}) + '\n')
        if self.path == '/redirect':
            self.send_response(302)
            self.send_header('Location', '/secret')
            self.send_header('Content-Length', '0')
            self.end_headers()
        elif self.path in ['/large', '/chunked']:
            self.send_response(200)
            if self.path == '/large':
                self.send_header('Content-Length', '1048576')
            self.end_headers()
            try:
                for _ in range(128):
                    self.wfile.write(b'x' * 8192)
                    self.wfile.flush()
            except (BrokenPipeError, ConnectionResetError, ssl.SSLError):
                pass
        else:
            self.send_response(204)
            self.send_header('Content-Length', '0')
            self.end_headers()

    def log_message(self, *_args):
        pass


server = http.server.HTTPServer(('127.0.0.1', 0), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(sys.argv[1], sys.argv[2])
server.socket = context.wrap_socket(server.socket, server_side=True)
print(server.server_port, flush=True)
server.serve_forever()
