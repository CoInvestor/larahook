<?php

namespace CoInvestor\LaraHook\Test;

use Orchestra\Testbench\TestCase;
use CoInvestor\LaraHook\Facades\Hook;

class HookTests extends TestCase
{
    /**
     * Setup Hook service
     *
     * @param  [type] $app [description]
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['CoInvestor\LaraHook\HookServiceProvider'];
    }

    /**
     * Confirm default value returns if no listeners
     * @return void
     */
    public function testGetDefaultValueReturns()
    {
        $result = Hook::get(
            "test_name",
            ['arg1', 'arg2'],
            function ($arg1, $arg2) {
                return $arg1 . $arg2;
            }
        );

        $this->assertEquals('arg1arg2', $result);
        $this->assertFalse(Hook::hasListeners('test_name'));
    }

    /**
     * Confirm listener runs when hook is called
     * @return void
     */
    public function testGetListenersRun()
    {
        Hook::listen("test_name", function ($callback, $output, $arg1, $arg2) {
            return "hooked" . $arg1 . $arg2 . $callback->call();
        }, 1);


        $result = Hook::get(
            "test_name",
            ['arg1', 'arg2'],
            function ($arg1, $arg2) {
                return $arg1 . $arg2;
            }
        );

        $this->assertEquals('hookedarg1arg2arg1arg2', $result);
        $this->assertTrue(Hook::hasListeners('test_name'));
    }

    /**
     * Check listener is passed default value from callback
     * when hook get is passed true.
     *
     * @return void
     */
    public function testGetListenersRunWithDefaultOn()
    {
        Hook::listen("test_name", function ($callback, $output, $arg1, $arg2) {
            return $output . "hooked";
        }, 1);

        $result = Hook::get(
            "test_name",
            ['arg1', 'arg2'],
            function ($arg1, $arg2) {
                return $arg1 . $arg2;
            },
            true
        );

        $this->assertEquals('arg1arg2hooked', $result);
    }

    /**
     * Confirm listeners can return falsey values
     * @return void
     */
    public function testGetListenersReturnFalsey()
    {
        Hook::listen("test_1", function ($callback, $output) {
            return false;
        });
        Hook::listen("test_2", function ($callback, $output) {
            return null;
        });
        Hook::listen("test_3", function ($callback, $output) {
            return '';
        });

        $this->assertFalse(Hook::get("test_1", [], function () {
            return true;
        }));
        $this->assertNull(Hook::get("test_2", [], function () {
            return true;
        }));
        $this->assertEquals('', Hook::get("test_3", [], function () {
            return true;
        }));
        $this->assertTrue(Hook::get("test_4", [], function () {
            return true;
        }));
    }

    /**
     * test stop function pauses listener queue
     * @return void
     */
    public function testStop()
    {
        Hook::listen("test", function ($callback, $output, $data) {
            return $data . $output . '1';
        });
        Hook::listen("test", function ($callback, $output, $data) {
            Hook::stop("test");
            return $output . '2';
        });
        Hook::listen("test", function ($callback, $output, $data) {
            return $output . '3';
        });
        Hook::listen("test", function ($callback, $output, $data) {
            return $output . '4';
        });

        // Ensure stop only persists for current chain
        $this->assertEquals('hello12', Hook::get("test", ['hello'], function () {
            return 'default';
        }));
        $this->assertEquals('hello12', Hook::get("test", ['hello'], function () {
            return 'default';
        }));

        // All listeners stopped - we should return default
        Hook::stop("test");
        $this->assertEquals('default', Hook::get("test", ['hello'], function () {
            return 'default';
        }));
    }

    /**
     * Confirm listeners return in priority order. Listeners at the same priority
     * should run in order they were added
     *
     * @return void
     */
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

    /**
     * Check get all listeners works correctly
     * @return void
     */
    public function testGetListenersGetAll()
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

    /**
     * Check if has listeners
     * @return void
     */
    public function testHasListeners()
    {
        $this->assertFalse(Hook::hasListeners('test'));

        Hook::listen("test", function ($callback, $output) {
            return "test";
        });

        $this->assertTrue(Hook::hasListeners('test'));
        $this->assertFalse(Hook::hasListeners('other'));
    }

    /**
     * Check we get correct events
     *
     * @return void
     */
    public function testGetEvents()
    {
        Hook::listen("test", function ($callback, $output) {
            return "";
        });
        Hook::listen("test", function ($callback, $output) {
            return "";
        });
        $results = Hook::getEvents('test');
        $this->assertCount(2, $results);
        $this->assertEquals('testGetEvents', $results[0]['function']);
        $this->assertEquals('CoInvestor\LaraHook\Test\HookTests', $results[0]['class']);
    }

    /**
     * Check get get correct hooks list
     *
     * @return void
     */
    public function testGetHooks()
    {
        Hook::listen("test", function ($callback, $output) {
            return "";
        });
        Hook::listen("test2", function ($callback, $output) {
            return "";
        });
        Hook::listen("test2", function ($callback, $output) {
            return "";
        });
        $results = Hook::getHooks('test');
        $this->assertCount(2, $results);
        $this->assertEquals('test', $results[0]);
        $this->assertEquals('test2', $results[1]);
    }

    /**
     * Check mocks set results
     *
     * @return void
     */
    public function testMock()
    {
        $result = Hook::get(
            "test_name",
            [],
            function () {
                return "default";
            }
        );

        $this->assertEquals($result, 'default');

        Hook::mock('test_name', 'mockvalue');

        $result = Hook::get(
            "test_name",
            [],
            function () {
                return "default";
            }
        );

        $this->assertEquals($result, 'mockvalue');


        Hook::mock('test_name', 'mockvalue2');

        $result = Hook::get(
            "test_name",
            [],
            function () {
                return "default";
            }
        );

        $this->assertEquals($result, 'mockvalue2');
    }

    /**
     * Remove a specific listener from hook.
     *
     * @return void
     */
    public function testRemoveListener()
    {
        $method = function ($callback, $output) {
            return $output . "AAA";
        };

        Hook::listen("test", $method);
        Hook::listen("test", function ($callback, $output) {
            return $output . "BBB";
        });

        $this->assertEquals('AAABBB', Hook::get("test", [], function () {
            return 'default';
        }));

        $this->assertTrue(Hook::removeListener('test', $method));

        $this->assertEquals('BBB', Hook::get("test", [], function () {
            return 'default';
        }));

        Hook::listen("test", function ($callback, $output) {
            return $output . "CCC";
        });

        $this->assertEquals('BBBCCC', Hook::get("test", [], function () {
            return 'default';
        }));
    }

    /**
     * Remove all listeners on a hook
     *
     * @return void
     */
    public function testRemoveListeners()
    {
        $method = function ($callback, $output) {
            return $output . "AAA";
        };

        Hook::listen("test", $method);
        Hook::listen("test", function ($callback, $output) {
            return $output . "BBB";
        });

        $this->assertEquals('AAABBB', Hook::get("test", [], function () {
            return 'default';
        }));

        $this->assertTrue(Hook::removeListeners('test'));

        $this->assertEquals('default', Hook::get("test", [], function () {
            return 'default';
        }));
    }
}
