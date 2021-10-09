<?php

namespace CoInvestor\LaraHook\Console;

use Illuminate\Console\Command;
use CoInvestor\LaraHook\Facades\Hook;

class HookListeners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hook:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all hook listeners';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $list = Hook::getListeners();
        $array = [];

        foreach ($list as $hook => $lister) {
            foreach ($lister as $key => $element) {
                $array[] = [
                    $hook,
                    $key,
                    $element['caller']['class'],
                ];
            }
        }

        $headers = ['Hook name', 'Order', 'Listener class'];

        $this->table($headers, $array);
    }
}
