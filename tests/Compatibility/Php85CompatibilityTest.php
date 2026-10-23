<?php

namespace EasyDingTalk\Tests\Compatibility;

use EasyDingTalk\Application;
use EasyDingTalk\Internal\Http\Client;
use EasyDingTalk\Internal\Http\Support\Collection;
use EasyDingTalk\Internal\Http\Support\XML;
use EasyDingTalk\Robot;
use EasyDingTalk\Tests\TestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Php85CompatibilityTest extends TestCase
{
    private function mockApplication(array $responses, array &$history): Application
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        $app = new Application(['app_key' => 'test-key', 'app_secret' => 'test-secret', 'http' => ['handler' => $stack]]);
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('access_token.test-key', ['access_token' => 'cached-test-token', 'expires_in' => 7200]);
        $app['cache'] = $cache;
        return $app;
    }

    public static function imCalls(): array
    {
        return [
            'department' => ['department', 'list', [null, true], 'GET', '/department/list', ['fetch_child' => 'true']],
            'users' => ['user', 'getDetailedUsers', [42, 0, 100], 'GET', '/user/listbypage', ['department_id' => '42', 'offset' => '0', 'size' => '100']],
            'schedules' => ['attendance', 'schedules', ['2026-10-23'], 'POST', '/topapi/attendance/listschedule', ['workDate' => '2026-10-23', 'offset' => null, 'size' => null]],
            'records' => ['attendance', 'records', [['userIds' => ['user-1'], 'checkDateFrom' => '2026-10-23']], 'POST', '/attendance/listRecord', ['userIds' => ['user-1'], 'checkDateFrom' => '2026-10-23']],
            'userGroup' => ['attendance', 'userGroup', ['user-1'], 'POST', '/topapi/attendance/getusergroup', ['userid' => 'user-1']],
            'results' => ['attendance', 'results', [['userIdList' => ['user-1'], 'offset' => 0, 'limit' => 50]], 'POST', '/attendance/list', ['userIdList' => ['user-1'], 'offset' => 0, 'limit' => 50]],
        ];
    }

    /** @dataProvider imCalls */
    public function testImContractsThroughRealGuzzleStack($service, $method, $args, $verb, $path, $body): void
    {
        $history = [];
        $expected = ['errcode' => 0, 'result' => ['items' => ['test']]];
        $app = $this->mockApplication([new Response(200, ['Content-Type' => 'application/json'], json_encode($expected))], $history);
        $this->assertSame($expected, $app->$service->$method(...$args));
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame($verb, $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('cached-test-token', $query['access_token']);
        if ($verb === 'GET') {
            foreach ($body as $key => $value) { $this->assertSame($value, $query[$key]); }
        } else {
            $this->assertSame($body, json_decode((string) $request->getBody(), true));
        }
    }

    public function testTokenRefreshUsesCredentialsAndCachesArrayResponse(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"errcode":0,"access_token":"fresh-test-token","expires_in":7200}'),
            new Response(200, [], '{"errcode":0,"userlist":[],"hasMore":false}'),
        ]));
        $stack->push(Middleware::history($history));
        $app = new Application(['app_key' => 'token-key', 'app_secret' => 'token-secret', 'http' => ['handler' => $stack]]);
        $cache = new Psr16Cache(new ArrayAdapter());
        $app['cache'] = $cache;
        $this->assertSame('fresh-test-token', $app->access_token->getToken());
        $this->assertSame('fresh-test-token', $app->access_token->getToken());
        $this->assertCount(1, $history);
        $this->assertSame('/gettoken', $history[0]['request']->getUri()->getPath());
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame(['appkey' => 'token-key', 'appsecret' => 'token-secret'], $query);
        $this->assertFalse($app->user->getDetailedUsers(1, 0, 100)['hasMore']);
        $this->assertCount(2, $history);
        $this->assertSame('fresh-test-token', $cache->get('access_token.token-key')['access_token']);
    }

    public function testInvalidTokenCredentialsRemainException(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"errcode":40013,"errmsg":"invalid appkey"}')]));
        $app = new Application(['app_key' => 'invalid-key', 'app_secret' => 'invalid-secret', 'http' => ['handler' => $stack]]);
        $app['cache'] = new Psr16Cache(new ArrayAdapter());
        $this->expectException(\EasyDingTalk\Kernel\Exceptions\InvalidCredentialsException::class);
        $app->access_token->refresh();
    }

    public function testMiddlewareRegistrationIsIdempotent(): void
    {
        $app = new Application();
        $app->user; $app->department; $app->attendance;
        $this->assertSame(['access_token', 'retry'], array_keys($app['client']->getMiddlewares()));
        $this->assertSame($app['client'], $app['client']->setMiddlewares($app['client']->getMiddlewares()));
    }

    public function testCollectionInterfacesAndSerialization(): void
    {
        $collection = new Collection(['nested' => ['value' => '中文'], 'zero' => 0]);
        $collection['next'] = 'value';
        unset($collection['next']);
        $this->assertFalse(isset($collection['next']));
        $this->assertSame('中文', $collection->get('nested.value'));
        $this->assertCount(2, $collection);
        $this->assertSame($collection->all(), iterator_to_array($collection));
        $this->assertSame($collection->all(), json_decode(json_encode($collection), true));
        $this->assertSame($collection->all(), unserialize(serialize($collection))->all());
        $legacy = new Collection();
        $legacy->unserialize($collection->serialize());
        $this->assertSame($collection->all(), $legacy->all());
        $restored = eval('return '.var_export($collection, true).';');
        $this->assertSame($collection->all(), $restored->all());
    }

    public function testResponseTypesArePreserved(): void
    {
        foreach (['array', 'collection', 'object', 'raw'] as $type) {
            $client = new Client(['response_type' => $type]);
            $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"errcode":0}') ]));
            $client->setHandlerStack($stack);
            $response = $client->get('https://test.invalid/response');
            if ($type === 'array') { $this->assertSame(['errcode' => 0], $response); }
            elseif ($type === 'collection') { $this->assertInstanceOf(Collection::class, $response); $this->assertSame(0, $response['errcode']); }
            elseif ($type === 'object') { $this->assertSame(0, $response->errcode); }
            else { $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response); }
        }
    }

    public function testMultipartKeepsExistingResource(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"errcode":0}')]));
        $stack->push(Middleware::history($history));
        $client = new Client(); $client->setHandlerStack($stack);
        $stream = fopen('php://memory', 'w+'); fwrite($stream, 'test-upload-content'); rewind($stream);
        try {
            $this->assertSame(['errcode' => 0], $client->upload('https://test.invalid/upload', ['media' => $stream]));
            $this->assertStringContainsString('test-upload-content', (string) $history[0]['request']->getBody());
        } finally { fclose($stream); }
    }

    public function testRobotSignedRequestUsesMockHttpClient(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"errcode":0}') ]));
        $stack->push(Middleware::history($history));
        $robot = Robot::create('test-robot-token', 'test-signing-secret');
        $robot->setHttpClient(new GuzzleClient(['handler' => $stack]));
        $message = ['msgtype' => 'text', 'text' => ['content' => 'test']];
        $this->assertSame(['errcode' => 0], $robot->send($message));
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('test-robot-token', $query['access_token']);
        $expected = base64_encode(hash_hmac('sha256', $query['timestamp']."\n".'test-signing-secret', 'test-signing-secret', true));
        $this->assertSame($expected, $query['sign']);
        $this->assertSame($message, json_decode((string) $history[0]['request']->getBody(), true));
    }

    public function testXmlDoesNotChangeEntityLoaderAndParsesResponse(): void
    {
        $this->assertSame(['name' => '中文', 'count' => '2'], XML::parse('<xml><name><![CDATA[中文]]></name><count>2</count></xml>'));
        $response = new \EasyDingTalk\Internal\Http\Responses\Response(200, ['Content-Type' => 'application/xml'], '<xml><name>test</name></xml>');
        $this->assertSame(['name' => 'test'], $response->toArray());
    }

    public function testXmlRejectsDtdWithoutResolvingEntities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        XML::parse('<!DOCTYPE xml [<!ENTITY x SYSTEM "file:///not-available">]><xml>&x;</xml>');
    }

    public function testOAuthRedirectAndStateStayCompatible(): void
    {
        $app = new Application(['oauth' => ['web' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => 'https://test.invalid/callback', 'scope' => 'snsapi_login',
        ]]]);
        $request = new \Symfony\Component\HttpFoundation\Request();
        $session = new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        );
        $request->setSession($session); $app['request'] = $request;
        $response = $app->oauth->use('web')->redirect();
        $this->assertSame(302, $response->getStatusCode());
        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('test-client', $query['appid']);
        $this->assertSame('https://test.invalid/callback', $query['redirect_uri']);
        $this->assertSame($session->get('state'), $query['state']);
        $this->assertSame(64, strlen($query['state']));
    }

    public function testCallbackDecryptAndResponseRoundTrip(): void
    {
        $app = new Application(['corp_id' => 'test-corp', 'token' => 'test-callback-token',
            'aes_key' => rtrim(base64_encode(str_repeat('k', 32)), '=')]);
        $payload = ['EventType' => 'user_add_org', 'UserId' => ['test-user']];
        $encrypted = json_decode($app->encryptor->encrypt(json_encode($payload), 'test-nonce', 123456), true);
        $app['request'] = \Symfony\Component\HttpFoundation\Request::create('/callback', 'POST', [
            'signature' => $encrypted['msg_signature'], 'nonce' => $encrypted['nonce'],
            'timestamp' => $encrypted['timeStamp'],
        ], [], [], [], json_encode(['encrypt' => $encrypted['encrypt']]));
        $seen = [];
        $app->server->push(function ($value) use (&$seen) { $seen[] = $value; });
        $response = $app->server->serve();
        $this->assertSame([$payload], $seen);
        $this->assertSame(200, $response->getStatusCode());
        $reply = json_decode($response->getContent(), true);
        $this->assertSame('success', $app->encryptor->decrypt($reply['encrypt'], $reply['msg_signature'], $reply['nonce'], $reply['timeStamp']));
    }

    public function testExpiredTokenResponseDoesNotIntroduceNewAutomaticReplay(): void
    {
        // 2.3.1 decider stops on the first response. Preserve that boundary here;
        // changing write retries must be a separate idempotency-reviewed change.
        $history = [];
        $app = $this->mockApplication([new Response(200, [], '{"errcode":40001,"errmsg":"expired"}')], $history);
        $this->assertSame(40001, $app->attendance->records(['userIds' => ['test']])['errcode']);
        $this->assertCount(1, $history);
    }

    public function testNestedConfigUpdatesDoNotLoseRootOrSiblings(): void
    {
        $config = new Collection(['app_key' => 'test-key', 'http' => ['timeout' => 10, 'headers' => ['X-Test' => 'yes']]]);
        $config->set('http.timeout', 20);
        $this->assertSame('test-key', $config->get('app_key'));
        $this->assertSame(20, $config->get('http.timeout'));
        $this->assertSame('yes', $config->get('http.headers.X-Test'));
        $this->assertSame('default', $config->get('http.missing', 'default'));
    }
}
