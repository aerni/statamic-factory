<?php

namespace Aerni\Factory\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class RunFactory extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:factory:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fake content with the factory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
    }
}
