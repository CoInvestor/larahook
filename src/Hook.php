<?php

namespace CoInvestor\LaraHook;

use Illuminate\Support\Arr;

class Hook
{
    protected $watch = [];
    protected $stop = [];
    protected $mock = [];
    protected $testing = false;

    /**
     * hasListeners
     * Check if a given hook has any listeners configured.
     *
     * @param  string $hook
     * @return boolean
     */
    public function hasListeners(string $hook): bool
    {
        return array_key_exists($hook, $this->watch) &&
                is_array($this->watch[$hook]) &&
                sizeof($this->watch[$hook]) > 0;
    }

    /**
     * Return the hook answer.
     *
     * @param string   $hook        - Hook name
     * @param array    $params      - Hook arguments
     * @param callable $callback    - Default value callback
     * @param bool     $useCallbackAsFirstListener - Run default value callback to use as first $output value
     *
     * @return null|void
     */
    public function get(
        string $hook,
        array $params = [],
        callable $callback = null,
        bool $useCallbackAsFirstListener = false
    ) {
        $callbackObject = $this->createCallbackObject($callback, $params);

        // Is a mock configured?
        if ($this->isHookMocked($hook)) {
            return $this->mock[$hook]['return'];
        }

        // If hook has listeners & isn't stopped, run them
        if ($this->hasListeners($hook) && empty($this->stop[$hook])) {
            return $this->run($hook, $params, $callbackObject, $useCallbackAsFirstListener);
        }

        unset($this->stop[$hook]);

        // No listeners, invoke default result
        return $callbackObject->call();
    }

    /**
     * Stop all another hook running.
     *
     * @param string $hook Hook name
     */
    public function stop(string $hook)
    {
        $this->stop[$hook] = true;
    }

    /**
     * Subscribe to hook.
     *
     * @param string $hook Hook name
     * @param $priority
     * @param $function
     */
    public function listen(string $hook, callable $function, int $priority = 0)
    {
        // Does hook exist? make array
        if (empty($this->watch[$hook])) {
            $this->watch[$hook] = [];
        }

        // Does priory exist in hook? make array
        if (empty($this->watch[$hook][$priority])) {
            $this->watch[$hook][$priority] = [];
        }

        // Nest final hook in priorty so that as many hooks as wanted can be set with the same
        // priorty level. Hooks at the same priorty will run in the order they are added.
        // getListeners method will flatten array to correct order when called
        $this->watch[$hook][$priority][] = [
            'function' => $function,
            'caller'   => $this->detectCallerInformation(),
        ];

        ksort($this->watch[$hook]);
    }

    /**
     * Remove all listeners on a particular hook
     *
     * @param  string $hook [description]
     * @return bool       [description]
     */
    public function removeListeners(string $hook): bool
    {
        $this->watch[$hook] = [];
        return true;
    }

    /**
     * Clear all existing listeners
     * @return bool [description]
     */
    public function clearListeners(): bool
    {
        $this->watch = [];
        return true;
    }

    /**
     * Reset all hooks. Returns to default state with any hooks, mocks or stops removed.
     *
     * @return  bool
     */
    public function reset(): bool
    {
        $this->clearListeners();
        $this->stop = [];
        $this->mock = [];
        $this->testing = true;
        return true;
    }

    /**
     * Remove specific listener
     *
     * @param  string   $hook     [description]
     * @param  callable $function [description]
     * @return [type]             [description]
     */
    public function removeListener(string $hook, callable $function): bool
    {
        foreach ($this->watch[$hook] as $priority => $hooks) {
            foreach ($hooks as $index => $h) {
                if ($h['function'] === $function) {
                    unset($this->watch[$hook][$priority][$index]);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return all registered hooks.
     *
     * @return array
     */
    public function getHooks(): array
    {
        $hookNames = (array_keys($this->watch));
        ksort($hookNames);

        return $hookNames;
    }

    /**
     * Return all listeners for hook.
     *
     * @param string $hook
     *
     * @return array
     */
    public function getEvents(string $hook): array
    {
        $output = [];

        foreach ($this->getListeners($hook) as $key => $value) {
            $output[$key] = $value['caller'];
        }

        return $output;
    }

    /**
     * For testing.
     *
     * @param string $hook   Hook name
     * @param mixed  $return Answer
     */
    public function mock(string $hook, $return)
    {
        $this->testing = true;
        $this->mock[$hook] = ['return' => $return];
    }

    /**
     * is a mock configured for this hook
     *
     * @param string $hook Hook name
     *
     * @return bool
     */
    protected function isHookMocked(string $hook): bool
    {
        return $this->testing && array_key_exists($hook, $this->mock);
    }

    /**
     * Return a new callback object.
     *
     * @param callable $callback function
     * @param array    $params   parameters
     *
     * @return \CoInvestor\LaraHook\Callback
     */
    protected function createCallbackObject(callable $callback = null, array $params = []): Callback
    {
        // Create void callback object if none set.
        if (!$callback) {
            $callback = function () {
            };
        }

        return new Callback($callback, $params);
    }

    /**
     * Run hook events.
     *
     * @param string    $hook           Hook name
     * @param array     $params         Parameters
     * @param Callback  $callback       Callback object
     * @param bool      $useCallbackAsFirstListener -
     *
     * @return mixed
     */
    protected function run(string $hook, array $params, Callback $callback, bool $useCallbackAsFirstListener)
    {
        // If useCallbackAsFirstListener is set, he $output value passed to the first
        // listener will be the result of the default callback.
        // If false, this will be null (replicating original behavior where the default
        // callback is not executed when a listener is set)
        array_unshift($params, $useCallbackAsFirstListener ? $callback->call() : null);
        array_unshift($params, $callback);

        if ($this->hasListeners($hook)) {
            foreach ($this->getListeners($hook) as $function) {
                if (!empty($this->stop[$hook])) {
                    unset($this->stop[$hook]);
                    break;
                }

                // array_values is used to ensure the args are passed by position. In php8
                // call_user_func_array will default to passing args as named values if the
                // array passed is associative. This breaks the existing functionality where
                // by the listener can name its arguments whatever it pleases.
                //
                // We may in the future want to do something clever re: detecting if this behavior
                // is wanted or not.
                $output = call_user_func_array($function['function'], array_values($params));
                $params[1] = $output;
            }
        }

        return $output;
    }

    /**
     * Return the listeners.
     *
     * @param string $hook If supplied, only listeners for the specified hook will be returned.
     * @return array
     */
    public function getListeners(string $hook = null): array
    {
        if (is_null($hook)) {
            return array_map(function ($hooks) {
                return array_merge(...$hooks);
            }, $this->watch);
        }

        return empty($this->watch[$hook]) ? [] : array_merge(...$this->watch[$hook]);
    }

    /**
     * Detect caller information
     * Determines file, class & line number that the given listener is defined on.
     *
     * @return array
     */
    private function detectCallerInformation(): array
    {
        // Use backtrace to determine where the current listener is defined.
        $trace = debug_backtrace(0, 5);
        // 0 is this method, and 1 is this libraries hook.
        $depth = 2;

        // if 2 is an include, or require, get method details from depth 3.
        if (in_array(Arr::get($trace[$depth], 'function'), ['include', 'require'])) {
            $depth++;
        }

        // Use $depth+1 to get function/class names, while depth gives us file/line of the
        // hook facade itself being invoked.
        return [
                'file' => $trace[$depth]['file'],
                'line' => $trace[$depth]['line'],
                'function' => $trace[$depth + 1]['function'],
                'class' => Arr::get($trace[$depth + 1], 'class'),
        ];
    }
}
