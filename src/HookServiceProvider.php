<?php

namespace CoInvestor\LaraHook;

use Blade;
use CoInvestor\LaraHook\Hook;
use Illuminate\Support\ServiceProvider;
use CoInvestor\LaraHook\Console\HookListeners;

class HookServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->bootDirectives();
    }

    public function register()
    {
        $this->commands([
            HookListeners::class,
        ]);

        $this->app->singleton('Hook', function () {
            return new Hook();
        });
    }

    protected function bootDirectives()
    {
        Blade::directive('hook', function ($parameter) {
            $parameter = trim($parameter, '()');
            $parameters = explode(',', $parameter);

            $name = trim($parameters[0], "'");

            // $parameters[1] => bool => is this wrapper component?
            if (!isset($parameters[1])) {
                return '<?php

                use CoInvestor\LaraHook\Facades\Hook;

                $__definedVars = (get_defined_vars()["__data"]);
                if (empty($__definedVars))
                {
                    $__definedVars = [];
                }
                $output = Hook::get(
                    "template.' . $name . '",
                    ["data"=>$__definedVars],
                    function($data) { return null; }
                );
                if ($output)
                echo $output;
                ?>';
            } else {
                return '<?php
                    $__hook_name="' . $name . '";
                    ob_start();
                ?>';
            }
        });

        Blade::directive('endhook', function ($parameter) {
            return '<?php
                use CoInvestor\LaraHook\Facades\Hook;

                $__definedVars = (get_defined_vars()["__data"]);
                if (empty($__definedVars))
                {
                    $__definedVars = [];
                }
                $__hook_content = ob_get_clean();

                $output = Hook::get(
                    "template.$__hook_name",
                    ["data"=>$__definedVars],
                    function($data) use ($__hook_content) { return $__hook_content; }
                );

                unset($__hook_name);
                unset($__hook_content);
                if ($output)
                    echo $output;
                ?>';
        });
    }
}
