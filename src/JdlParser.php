<?php

namespace Nacer\JdlToFilament;

use Symfony\Component\Process\Process;

class JdlParser
{
    /**
     * Path to the Node script that does the actual JDL parsing.
     */
    protected string $nodeScriptPath;

    /**
     * Resolution order: explicit constructor argument, then the
     * consuming app's config('jdl-to-filament.node_script_path') if
     * they've overridden it, then the copy of node/parse.js this
     * package ships with (one directory up from src/, i.e. the
     * package root - works identically whether this package is used
     * via a path repository during development or installed for real
     * under vendor/nacer/jdl-to-filament).
     */
    public function __construct(?string $nodeScriptPath = null)
    {
        $this->nodeScriptPath = $nodeScriptPath
            ?? (function_exists('config') ? config('jdl-to-filament.node_script_path') : null)
            ?? dirname(__DIR__).'/node/parse.js';
    }

    /**
     * Parse a .jdl file and return its data as a PHP array.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function parse(string $jdlFilePath): array
    {
        if (! file_exists($this->nodeScriptPath)) {
            return [
                'success' => false,
                'error' => "Node script not found at: {$this->nodeScriptPath}",
            ];
        }

        if (! file_exists($jdlFilePath)) {
            return [
                'success' => false,
                'error' => "JDL file not found at: {$jdlFilePath}",
            ];
        }

        $process = new Process(['node', $this->nodeScriptPath, $jdlFilePath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'success' => false,
                'error' => trim($process->getErrorOutput()) ?: trim($process->getOutput()),
            ];
        }

        $output = trim($process->getOutput());
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Failed to decode JSON from the node parser: '
                    . json_last_error_msg()
                    . "\nRaw output:\n" . $output,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
