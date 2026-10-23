<?php

/*
 * This file is part of the mingyoung/dingtalk.
 *
 * (c) 张铭阳 <mingyoungcheung@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyDingTalk\Tests\Compatibility;

use EasyDingTalk\Chat\Client as ChatClient;
use EasyDingTalk\Checkin\Client as CheckinClient;
use EasyDingTalk\Contact\Client as ContactClient;
use EasyDingTalk\Department\Client as DepartmentClient;
use EasyDingTalk\H5app\Client as H5appClient;
use EasyDingTalk\Process\Client as ProcessClient;
use EasyDingTalk\Role\Client as RoleClient;
use EasyDingTalk\Tests\TestCase;
use EasyDingTalk\User\Client as UserClient;
use ReflectionMethod;

/**
 * 2.3.2 引入的命名（getInfo / getUserInfo）在 3.0 中的兼容别名。
 */
class AliasesTest extends TestCase
{
    /** @test */
    public function aliasesExist()
    {
        $map = [
            ChatClient::class => 'getInfo',
            CheckinClient::class => 'getInfo',
            ContactClient::class => 'getInfo',
            DepartmentClient::class => 'getInfo',
            H5appClient::class => 'getInfo',
            ProcessClient::class => 'getInfo',
            RoleClient::class => 'getInfo',
            UserClient::class => 'getUserInfo',
        ];

        foreach ($map as $class => $method) {
            $reflection = new ReflectionMethod($class, $method);
            $this->assertTrue($reflection->isPublic(), "$class::$method 必须是 public");
            $this->assertSame(
                $this->expectedRequired($class),
                $reflection->getNumberOfRequiredParameters(),
                "$class::$method 必填参数个数应与 get() 一致"
            );
        }
    }

    /** @test */
    public function chatGetInfoDelegatesToGet()
    {
        $this->make(ChatClient::class)->getInfo('chat-id')
            ->assertGetUri('chat/get')->assertQuery(['chatid' => 'chat-id']);
    }

    /** @test */
    public function checkinGetInfoDelegatesToGet()
    {
        $this->make(CheckinClient::class)->getInfo($params = ['foo' => 'bar'])
            ->assertPostUri('topapi/checkin/record/get')->assertPostJson($params);
    }

    /** @test */
    public function contactGetInfoDelegatesToGet()
    {
        $this->make(ContactClient::class)->getInfo('user-1')
            ->assertPostUri('topapi/extcontact/get')->assertPostJson(['user_id' => 'user-1']);
    }

    /** @test */
    public function departmentGetInfoDelegatesToGet()
    {
        $this->make(DepartmentClient::class)->getInfo('dept-1')
            ->assertGetUri('department/get')->assertQuery(['id' => 'dept-1', 'lang' => null]);
    }

    /** @test */
    public function processGetInfoDelegatesToGet()
    {
        $this->make(ProcessClient::class)->getInfo('instance-1')
            ->assertPostUri('topapi/processinstance/get')->assertPostJson(['process_instance_id' => 'instance-1']);
    }

    /** @test */
    public function roleGetInfoDelegatesToGet()
    {
        $this->make(RoleClient::class)->getInfo('role-1')
            ->assertPostUri('topapi/role/getrole')->assertPostJson(['roleId' => 'role-1']);
    }

    /** @test */
    public function userGetUserInfoDelegatesToGet()
    {
        $this->make(UserClient::class)->getUserInfo('user-1')
            ->assertGetUri('user/get')->assertQuery(['userid' => 'user-1', 'lang' => null]);
    }

    /**
     * get() 的必填参数个数（用于校验别名签名一致）。
     */
    private function expectedRequired($class)
    {
        $aliases = [
            ChatClient::class => 1,
            CheckinClient::class => 1,
            ContactClient::class => 1,
            DepartmentClient::class => 1,
            H5appClient::class => 0,
            ProcessClient::class => 1,
            RoleClient::class => 1,
            UserClient::class => 1,
        ];

        return $aliases[$class];
    }
}
