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

    public function testWrappedBladeHook()
    {
        $view = $this->blade('<p>Hi @hook(\'test\', true){{$name}}@endhook</p>', ['name' => 'Bob>']);
        $view->assertSee('Hi Bob');

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return $this->blade('<strong>{{ $name }}</strong>', $variables);
        });

        $view = $this->blade('<p>Hi @hook(\'test\', true){{ $name }}@endhook</p>', ['name' => 'Bob>']);
        $view->assertSee('<p>Hi <strong>Bob&gt;</strong></p>', false);

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return "Battlemaster $output";
        });

        $view = $this->blade('<p>Hi @hook(\'test\', true){{ $name }}@endhook</p>', ['name' => 'Bob>']);
        $view->assertSee('<p>Hi Battlemaster <strong>Bob&gt;</strong></p>', false);
    }

    public function testSingleBladeHook()
    {
        $view = $this->blade('<p>Hi @hook(\'test\')</p>', ['name' => 'Sally']);
        $view->assertSee('<p>Hi </p>', false);

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return $this->blade('{{ $name }}', $variables);
        });

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return "Battlemaster $output";
        });

        $view = $this->blade('<p>Hi @hook(\'test\')</p>', ['name' => 'Sally']);
        $view->assertSee('<p>Hi Battlemaster Sally</p>', false);
    }

    public function testMultipleBladeHook()
    {
        Hook::listen('template.test', function ($callback, $output, $variables) {
            return $this->blade('{{ $name }}', $variables);
        });

        Hook::listen('template.thing', function ($callback, $output, $variables) {
            return $this->blade('science', $variables);
        });


        $view = $this->blade('<p>Hi @hook(\'test\') I like @hook(\'thing\')</p>', ['name' => 'Sally']);
        $view->assertSee('<p>Hi Sally I like science</p>', false);
    }

    public function testWrappedBladeHookManipulateInner()
    {
        $view = $this->blade('@hook(\'test\', true)This is some text.@endhook');
        $view->assertSee('This is some text.');

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return str_replace('text', 'great text', $output);
        });

        $view = $this->blade('@hook(\'test\', true)This is some text.@endhook');
        $view->assertSee('This is some great text.');

        Hook::listen('template.test', function ($callback, $output, $variables) {
            return $output . "<div>Additional Element</div>";
        });

         $view = $this->blade('@hook(\'test\', true)This is some text.@endhook');
        $view->assertSee('This is some great text.<div>Additional Element</div>', false);
    }
}
