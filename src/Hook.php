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
     * @param string   $hook        Hook name
     * @param array    $params
     * @param callable $callback
     * @param string   $htmlContent content wrapped by hook
     *
     * @return null|void
     */
    public function get(string $hook, $params = [], callable $callback = null, $htmlContent = '')
    {
        $callbackObject = $this->createCallbackObject($callback, $params);

        $output = $this->returnMockIfDebugModeAndMockExists($hook);
        if ($output) {
            return $output;
        }

        // If hook has listeners, run them
        if ($this->hasListeners($hook)) {
            return $this->run($hook, $params, $callbackObject, $htmlContent);
        }

        // No listerns, invoke default result
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
        $caller = debug_backtrace(null, 3)[2];

        if (in_array(Arr::get($caller, 'function'), ['include', 'require'])) {
            $caller = debug_backtrace(null, 4)[3];
        }

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
            'caller'   => [
                // 'file' => $caller['file'],
                // 'line' => $caller['line'],
                'class' => Arr::get($caller, 'class'),
            ],
        ];

        ksort($this->watch[$hook]);
    }

    /**
     * Return all registered hooks.
     *
     * @return array
     */
    public function getHooks()
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
    public function getEvents(string $hook)
    {
        $output = [];

        foreach ($this->watch[$hook] as $key => $value) {
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
     * Return the mock value.
     *
     * @param string $hook Hook name
     *
     * @return null|mixed
     */
    protected function returnMockIfDebugModeAndMockExists(string $hook)
    {
        if ($this->testing) {
            if (array_key_exists($hook, $this->mock)) {
                $output = $this->mock[$hook]['return'];
                unset($this->mock[$hook]);

                return $output;
            }
        }
    }

    /**
     * Return a new callback object.
     *
     * @param callable $callback function
     * @param array    $params   parameters
     *
     * @return \CoInvestor\LaraHook\Callback
     */
    protected function createCallbackObject($callback, $params)
    {
        return new Callback($callback, $params);
    }

    /**
     * Run hook events.
     *
     * @param string                $hook     Hook name
     * @param array                 $params   Parameters
     * @param \CoInvestor\LaraHook\Callback $callback Callback object
     * @param string                $output   html wrapped by hook
     *
     * @return mixed
     */
    protected function run(string $hook, array $params, Callback $callback, $output = null)
    {
        array_unshift($params, $output);
        array_unshift($params, $callback);

        if ($this->hasListeners($hook)) {
            foreach ($this->getListeners($hook) as $function) {
                if (!empty($this->stop[$hook])) {
                    unset($this->stop[$hook]);
                    break;
                }

                $output = call_user_func_array($function['function'], $params);
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
    public function getListeners(string $hook = null)
    {
        if (is_null($hook)) {
            return array_map(function ($hooks) {
                return array_merge(...$hooks);
            }, $this->watch);
        }

        return empty($this->watch[$hook]) ? null : array_merge(...$this->watch[$hook]);
    }
}
