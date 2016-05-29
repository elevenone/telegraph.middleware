<?php
namespace Telegraph\Middleware;

use Zend\Diactoros\Response;

class JsonContentHandlerTest extends AbstractContentHandlerTest
{
    public function dataRequest()
    {
        $data = [];

        foreach ([
            'POST',
            'PUT',
            'DELETE',
            'OPTIONS',
            'other',
        ] as $method) {
            foreach ([
                'application/json',
                'application/json;charset=utf-8',
                'application/json; charset=utf-8',
                'application/json ; charset=utf-8',
                'application/vnd.api+json',
                'application/vnd.custom+json',
            ] as $mime) {
                foreach ([
                    (object) ['foo' => 'bar'],
                    [1, 2, 3],
                    'strings',
                    3.14159,
                    42,
                    true,
                    false,
                    null,
                ] as $value) {
                    $data[] = [$method, $mime, $value, json_encode($value)];
                }
            }
        }

        return $data;
    }

    /**
     * @dataProvider dataRequest
     */
    public function testInvoke($method, $mime, $body, $json)
    {
        if (is_array($body) && gettype(key($body)) === 'string') {
            // Convert the associative array to an object
            $body = json_decode(json_encode($body));
        }

        $request = $this->getRequest($method, $mime, $json);

        $next = function ($request) use ($mime, $body) {
            $this->assertSame($mime, $request->getHeaderLine('Content-Type'));
            $this->assertEquals($body, $request->getParsedBody());
            return new Response();
        };

        $handler = new JsonContentHandler();
        $resolved = $handler($request, $next);
    }

    public function testInvokeAsArray()
    {
        $body = [
            'foo' => 'bar',
            'array' => true,
        ];

        $request = $this->getRequest('POST', 'application/json', json_encode($body));

        $next = function ($request) use ($body) {
            $this->assertEquals($body, $request->getParsedBody());
            return new Response();
        };

        $handler = new JsonContentHandler(true);
        $resolved = $handler($request, $next);
    }

    public function testInvokeWithInvalidMethods()
    {
        $request = $this->getRequest('GET', 'application/json', null);

        $next = function ($request) {
            $this->assertEmpty($request->getParsedBody());
            return new Response();
        };

        $handler = new JsonContentHandler(true);

        $resolved = $handler($request, $next);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegex /error parsing json: .+/i
     */
    public function testInvokeWithMalformedBody()
    {
        $request = $this->getRequest(
            $method = 'POST',
            $mime = 'application/json',
            $body = '{'
        );

        $next = function ($request) {
            return new Response();
        };

        $handler = new JsonContentHandler();
        $resolved = $handler($request, $next);
    }

    public function testInvokeWithNonApplicableMimeType()
    {
        $request = $this->getRequest(
            $method = 'POST',
            $mime = 'application/x-www-form-urlencoded',
            $body = http_build_query(['test' => 'form'], '', '&')
        );

        $next = function ($request) use ($mime) {
            $this->assertSame($mime, $request->getHeaderLine('Content-Type'));
            $this->assertEmpty($request->getParsedBody());
            return new Response();
        };

        $handler = new JsonContentHandler();

        $resolved = $handler($request, $next);
    }
}
