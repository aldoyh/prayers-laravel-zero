<?php

namespace App\Commands;

use App\Services\VersionService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildCommand extends Command
{
    protected $signature = 'app:build
                            {name? : The build name (default: prayers-cli)}
                            {--timeout=300 : The timeout in seconds or 0 to disable}';

    protected $description = 'Build a single-file executable (auto-increments patch version)';

    public function handle(): int
    {
        // --- 1. Auto-increment patch version (always, no flag needed) ---
        $versionService = new VersionService;
        $currentVersion = $versionService->getCurrentVersion();
        $newVersion = $versionService->incrementVersion($currentVersion);
        $versionService->updateVersion($newVersion);

        $this->line("  Version: <fg=yellow>$currentVersion</> → <fg=green>$newVersion</>");

        // --- 2. Resolve build name ---
        $name = $this->argument('name') ?? 'prayers-cli';

        // --- 3. Snapshot files that will be temporarily modified ---
        $configPath = config_path('app.php');
        $boxPath = base_path('box.json');
        $originalConfig = file_get_contents($configPath);
        $originalBox = file_get_contents($boxPath);

        try {
            // --- 4. Write production config (env=production, embedded version) ---
            $config = require $configPath;
            $config['env'] = 'production';
            $config['version'] = $newVersion;
            file_put_contents(
                $configPath,
                '<?php return ' . var_export($config, true) . ';' . PHP_EOL
            );

            // --- 5. Inject binary name into box.json ---
            $box = json_decode($originalBox, true);
            $box['main'] = 'prayers-cli';
            file_put_contents($boxPath, json_encode($box, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // --- 6. Locate box binary (bundled with laravel-zero/framework) ---
            $boxBin = base_path('vendor/laravel-zero/framework/bin/box');
            if (! file_exists($boxBin)) {
                $this->error("Box binary not found at: $boxBin");

                return 1;
            }

            $timeout = (float) $this->option('timeout') ?: null;

            $process = new Process(
                [PHP_BINARY, $boxBin, 'compile', '--working-dir='.base_path(), '--config='.base_path('box.json')],
                dirname($boxBin),
                null,
                null,
                $timeout
            );

            // --- 7. Compile ---
            $this->line('  <fg=yellow>Compiling PHAR...</>');
            $this->newLine();

            $process->run(function ($type, $data) {
                $this->line(rtrim($data));
            });

            if (! $process->isSuccessful()) {
                $this->newLine();
                $this->error('Build failed.');
                $this->line($process->getErrorOutput());

                return 1;
            }

            // --- 8. Move PHAR to builds/ ---
            $pharSrc = base_path('prayers-cli.phar');
            $buildsDir = base_path('builds');

            if (! is_dir($buildsDir)) {
                mkdir($buildsDir, 0755, true);
            }

            if (file_exists($pharSrc)) {
                rename($pharSrc, "$buildsDir/$name");
                chmod("$buildsDir/$name", 0755);
            }

            $this->newLine();
            $this->line("  <fg=green>Build complete:</> builds/<fg=cyan>$name</> (v<fg=cyan>$newVersion</>)");

        } finally {
            // --- 9. Always restore original files ---
            file_put_contents($configPath, $originalConfig);
            file_put_contents($boxPath, $originalBox);
        }

        return 0;
    }
}
