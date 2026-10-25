<?php
namespace EasyDingTalk\Tests\Compatibility;

use EasyDingTalk\Application;
use EasyDingTalk\Internal\Http\Client;
use EasyDingTalk\Tests\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

class StrictRequestTest extends TestCase
{
    public function testChangingStackAfterRequestRebuildsOwnedClient(): void
    {
        $client=new Client();
        $first=HandlerStack::create(new MockHandler([new Response(200,[],'{"result":1}')]));
        $second=HandlerStack::create(new MockHandler([new Response(200,[],'{"result":2}')]));
        $client->setHandlerStack($first);
        $this->assertSame(['result'=>1],$client->get('https://test.invalid/first'));
        $client->setHandlerStack($second);
        $this->assertSame(['result'=>2],$client->get('https://test.invalid/second'));
        $this->assertSame($second,$client->getHttpClient()->getConfig('handler'));
    }

    public function testCallbackParameterPrecedenceMatchesPreviousSymfonyContract(): void
    {
        $app=new Application();
        $request=new Request(['signature'=>'query'],['signature'=>'post'],['signature'=>'attribute']);
        $app['request']=$request;
        $server=$app->server;
        $method=new \ReflectionMethod($server,'requestParameter');
        $this->assertSame('attribute',$method->invoke($server,'signature'));
        $request->attributes->remove('signature');
        $this->assertSame('query',$method->invoke($server,'signature'));
        $request->query->remove('signature');
        $this->assertSame('post',$method->invoke($server,'signature'));
        $request->attributes->set('signature',null);
        $this->assertNull($method->invoke($server,'signature'));
        $request->attributes->remove('signature');
        $request->query->set('signature',['unchanged']);
        $this->assertSame(['unchanged'],$method->invoke($server,'signature'));
        $this->assertNull($method->invoke($server,'missing'));
    }
}
