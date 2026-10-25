<?php

/*
 * This file is part of the mingyoung/dingtalk.
 *
 * (c) 张铭阳 <mingyoungcheung@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyDingTalk\Kernel;

use EasyDingTalk\Kernel\Exceptions\InvalidArgumentException;
use EasyDingTalk\Kernel\Exceptions\RuntimeException;
use function EasyDingTalk\tap;
use Symfony\Component\HttpFoundation\Response;

class Server
{
    /**
     * @var \EasyDingTalk\Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $handlers = [];

    /**
     * @param \EasyDingTalk\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Handle the request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function serve()
    {
        foreach ($this->handlers as $handler) {
            $handler->__invoke($this->getPayload());
        }

        $this->app['logger']->debug('Request received: ', [
            'method' => $this->app['request']->getMethod(),
            'uri' => $this->app['request']->getUri(),
            'content' => $this->app['request']->getContent(),
        ]);

        return tap(new Response(
            $this->app['encryptor']->encrypt('success'), 200, ['Content-Type' => 'application/json']
        ), function ($response) {
            $this->app['logger']->debug('Response created:', ['content' => $response->getContent()]);
        });
    }

    /**
     * Push handler.
     *
     * @param \Closure|string|object $handler
     *
     * @return void
     *
     * @throws \EasyDingTalk\Kernel\Exceptions\InvalidArgumentException
     */
    public function push($handler)
    {
        if (is_string($handler)) {
            $handler = function ($payload) use ($handler) {
                return (new $handler($this->app))->__invoke($payload);
            };
        }

        if (!is_callable($handler)) {
            throw new InvalidArgumentException('Invalid handler');
        }

        array_push($this->handlers, $handler);
    }

    /** Preserve Symfony Request::get() precedence without its deprecated API. */
    protected function requestParameter(string $key)
    {
        $request = $this->app['request'];
        if ($request->attributes->has($key)) { return $request->attributes->all()[$key]; }
        if ($request->query->has($key)) { return $request->query->all()[$key]; }
        return $request->request->all()[$key] ?? null;
    }

    /**
     * Get request payload.
     * @return array
     */
    public function getPayload()
    {
        $payload = json_decode($this->app['request']->getContent(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('No payload received');
        }

        $result = $this->app['encryptor']->decrypt(
            $payload['encrypt'], $this->requestParameter('signature'), $this->requestParameter('nonce'), $this->requestParameter('timestamp')
        );

        return json_decode($result, true);
    }
}
