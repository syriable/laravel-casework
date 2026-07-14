<?php

namespace Syriable\Casework\Commands;

use Illuminate\Console\Command;

class CaseworkCommand extends Command
{
    public $signature = 'laravel-casework';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
