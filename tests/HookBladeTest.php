<?php

namespace CoInvestor\LaraHook\Test;

use Orchestra\Testbench\TestCase;
use CoInvestor\LaraHook\Facades\Hook;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;

class HookBladeTest extends TestCase
{
    use InteractsWithViews;

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

    public function testWrappedHook()
    {
        $view = $this->blade('Hi @hook(\'test\', true){{$name}}@endhook', ['name' => 'Bob']);
       // dd($view);
        $view->assertSee('Hi Bob');

        Hook::listen('template.test', function ($callback, $output, $variables) {
          return $this->blade('<strong>{{ $name }}</strong>', $variables);
        });

        $view = $this->blade('Hi @hook(\'test\', true){{ $name }}@endhook', ['name' => 'Bob']);
        $view->assertSee('Hi <strong>Bob</strong>');
    }
}
