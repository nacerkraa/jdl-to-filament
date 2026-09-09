<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallNodeCommand extends Command
{
    protected $signature = 'jdl:install-node';

    protected $description = 'Run "npm install" inside this package\'s bundled node/ directory (one-time setup, needed before any jdl:* command will work)';

    public function handle(): int
    {
        $nodeDir = dirname(__DIR__, 3).'/node';

        if (! is_dir($nodeDir)) {
            $this->error("Could not find the package's node/ directory at: {$nodeDir}");

            return self::FAILURE;
        }

        if (! file_exists($nodeDir.'/package.json')) {
            $this->error("No package.json found in {$nodeDir} - the package installation looks incomplete.");

            return self::FAILURE;
        }

        $this->info("Running npm install in {$nodeDir} ...");

        $process = new Process(['npm', 'install'], $nodeDir);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('npm install failed. Make sure Node.js and npm are installed and on your PATH.');

            return self::FAILURE;
        }

        $this->info('Done. You can now run jdl:generate, jdl:migrations, jdl:models, and jdl:filament.');

        return self::SUCCESS;
    }
}
