<?php

namespace CoInvestor\LaraHook\Test;

use Orchestra\Testbench\TestCase;
use CoInvestor\LaraHook\Facades\Hook;

class HookCommandTests extends TestCase
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

    public function testHookListCommand()
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
        Hook::listen("test3", function ($callback, $output) {
            return "";
        });

        $this->artisan('hook:list')
         ->expectsTable(
             [
                'Hook name', 'Order', 'Listener class'
             ],
             [
                [ 'test', '0', 'CoInvestor\LaraHook\Test\HookCommandTests'],
                [ 'test2', '0', 'CoInvestor\LaraHook\Test\HookCommandTests'],
                [ 'test2', '1', 'CoInvestor\LaraHook\Test\HookCommandTests'],
                [ 'test3', '0', 'CoInvestor\LaraHook\Test\HookCommandTests'],
             ]
         )
        ->assertExitCode(0);
    }
}
