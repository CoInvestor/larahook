<?php

namespace CoInvestor\LaraHook\Test;

use Orchestra\Testbench\TestCase;
use CoInvestor\LaraHook\Facades\Hook;

class HookTests extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['CoInvestor\LaraHook\HookServiceProvider'];
    }

    public function testGetDefaultValueReturns()
    {
        $result = Hook::get("test_name", ['arg1', 'arg2'], function ($arg1, $arg2) {

            return $arg1 . $arg2;
        });

        $this->assertEquals('arg1arg2', $result);
        $this->assertFalse(Hook::hasListeners('test_name'));
    }

    public function testGetListenerRun()
    {
        Hook::listen("test_name", function ($callback, $output, $arg1, $arg2) {

            return "hooked" . $arg1 . $arg2 . $callback->call();
        }, 1);
        $result = Hook::get("test_name", ['arg1', 'arg2'], function ($arg1, $arg2) {

            return $arg1 . $arg2;
        });

        $this->assertEquals('hookedarg1arg2arg1arg2', $result);
        $this->assertTrue(Hook::hasListeners('test_name'));
    }

    public function testGetListenersReturnsCorrectOrder()
    {
        Hook::listen("a", function ($callback, $output, $arg1, $arg2) {

            return "forth";
        }, 10);
        Hook::listen("a", function ($callback, $output, $arg1, $arg2) {

            return "second";
        }, 1);
        Hook::listen("a", function ($callback, $output, $arg1, $arg2) {

            return "third";
        }, 1);
        Hook::listen("a", function ($callback, $output, $arg1, $arg2) {

            return "first";
        });

        $listeners = Hook::getListeners("a");
        $this->assertCount(4, $listeners);
        $this->assertEquals('first', $listeners[0]['function']('a','b','c','d'));
        $this->assertEquals('second', $listeners[1]['function']('a','b','c','d'));
        $this->assertEquals('third', $listeners[2]['function']('a','b','c','d'));
        $this->assertEquals('forth', $listeners[3]['function']('a','b','c','d'));
    }

    public function testGetAllListeners()
    {
        Hook::listen("hello", function ($callback, $output, $arg1, $arg2) {

            return "hello";
        });
        Hook::listen("goodbye", function ($callback, $output, $arg1, $arg2) {

            return "goodbye";
        });
        Hook::listen("goodbye", function ($callback, $output, $arg1, $arg2) {

            return "betterbye";
        });

        $listeners = Hook::getListeners();
        $this->assertCount(2, $listeners);
        $this->assertCount(1, $listeners['hello']);
        $this->assertCount(2, $listeners['goodbye']);
    }
}
