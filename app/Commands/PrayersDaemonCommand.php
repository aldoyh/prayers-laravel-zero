<?php

namespace App\Commands;

use App\Services\PrayerTimesService;
use Exception;
use Illuminate\Console\Command;
use Phar;

class PrayersDaemonCommand extends Command
{
    protected $signature = 'prayers:daemon
                            {action : Action to perform (start|stop|restart|status|logs)}
                            {--city=Manama : City name}
                            {--country=Bahrain : Country name or ISO code}
                            {--method=10 : Calculation method ID}
                            {--timezone=Asia/Bahrain : Timezone}
                            {--variant=doha : Adhan variant}
                            {--player=auto : Audio player}
                            {--grace=2 : Grace window in minutes}
                            {--lines=50 : Number of log lines to show (logs action)}';

    protected $description = 'Manage the automatic Adhan daemon (start|stop|restart|status|logs)';

    private string $label = 'com.prayers-cli.adhan-daemon';

    private const VALID_ACTIONS = ['start', 'stop', 'restart', 'status', 'logs'];

    public function handle(): int
    {
        /** @var string $action */
        $action = $this->argument('action');

        if (! in_array($action, self::VALID_ACTIONS, true)) {
            $this->error("Invalid action: \"$action\". Valid: " . implode('|', self::VALID_ACTIONS));

            return 1;
        }

        return match ($action) {
            'start'   => $this->start(),
            'stop'    => $this->stop(),
            'restart' => $this->restart(),
            'status'  => $this->status(),
            'logs'    => $this->logs(),
        };
    }

    // -------------------------------------------------------------------------
    // Start
    // -------------------------------------------------------------------------

    private function start(): int
    {
        $city = $this->option('city');
        $country = $this->option('country');
        $method = $this->option('method');
        $timezone = $this->option('timezone');
        $variant = $this->option('variant');
        $player = $this->option('player');
        $grace = $this->option('grace');

        $cliPath = $this->resolveCliPath();
        if ($cliPath === null) {
            $this->error('prayers-cli binary not found. Build it first:');
            $this->line('  php prayers-cli app:build');

            return 1;
        }

        $destPath = $this->getPlistPath();
        $plistPath = $this->generatePlist(
            $cliPath,
            $city,
            $country,
            $method,
            $timezone,
            $variant,
            $player,
            $grace,
        );

        // Create LaunchAgents directory
        $launchAgentDir = $this->getLaunchAgentDir();
        if (! is_dir($launchAgentDir)) {
            mkdir($launchAgentDir, 0755, true);
        }

        if (! copy($plistPath, $destPath)) {
            $this->error("Failed to copy plist to $destPath");

            return 1;
        }

        $this->line("  ✅ Plist installed: <fg=cyan>$destPath</>");
        $this->line('');

        // Unload any existing instance, then load fresh
        exec("launchctl unload $destPath 2>&1", $unloadOutput, $unloadCode);
        exec("launchctl load $destPath 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            $this->warn('  Could not load LaunchAgent: ' . implode("\n", $output));
            $this->line('  You can manually load it:');
            $this->line("    launchctl load $destPath");
        } else {
            $this->line('  ✅ LaunchAgent loaded successfully!');
        }

        $this->line('');
        $this->line('  The daemon will run every 60 seconds and play Adhan at prayer times.');
        $this->line('  View status: <fg=yellow>php prayers-cli prayers:daemon status</>');
        $this->line('  View logs:   <fg=yellow>php prayers-cli prayers:daemon logs</>');
        $this->line('  Stop:        <fg=yellow>php prayers-cli prayers:daemon stop</>');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Stop
    // -------------------------------------------------------------------------

    private function stop(): int
    {
        $plistPath = $this->getPlistPath();

        if (! file_exists($plistPath)) {
            $this->warn('Daemon is not installed (no plist found).');

            return 0;
        }

        exec("launchctl unload $plistPath 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            $this->warn('Could not unload: ' . implode("\n", $output));
        } else {
            $this->line('  ✅ Daemon stopped (LaunchAgent unloaded).');
        }

        // Remove the plist
        if (unlink($plistPath)) {
            $this->line('  ✅ Plist removed.');
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Restart
    // -------------------------------------------------------------------------

    private function restart(): int
    {
        $this->line('  Restarting daemon...');
        $this->stop();
        $this->line('');
        $this->start();

        return 0;
    }

    // -------------------------------------------------------------------------
    // Status
    // -------------------------------------------------------------------------

    private function status(): int
    {
        $plistPath = $this->getPlistPath();

        $this->line('');
        $this->line('  <fg=cyan>Adhan Daemon Status</>');
        $this->line('  ' . str_repeat('─', 40));
        $this->line('');

        // Check if plist exists
        if (! file_exists($plistPath)) {
            $this->line('  ❌ Daemon is <fg=red>not installed</>');
            $this->line('');
            $this->line('  Install it:  <fg=yellow>php prayers-cli prayers:daemon start</>');

            return 1;
        }

        $this->line('  ✅ Plist: <fg=green>installed</>');
        $this->line("     <fg=white>~/$this->label.plist</>");
        $this->line('');

        // Check if loaded via launchctl
        exec("launchctl list $this->label 2>&1", $output, $exitCode);

        if ($exitCode === 0) {
            $this->line('  ✅ <fg=green>Daemon is running</>');

            // Parse launchctl output: PID, ExitCode, Label
            $parts = preg_split('/\s+/', $output[0] ?? '');
            $pid = $parts[0] ?? '?';
            $lastExit = $parts[1] ?? '?';
            $this->line("     PID: <fg=cyan>$pid</>");
            $this->line("     Last exit: <fg=cyan>$lastExit</>");

            // Check if it has a non-zero exit (crash loop)
            if (is_numeric($lastExit) && (int) $lastExit !== 0) {
                $this->warn('     ⚠️  Daemon exited with code ' . $lastExit . ' — check logs');
            }
        } else {
            $this->line('  ❌ <fg=yellow>Daemon is installed but not running</>');
            $this->line('  Start it:  <fg=yellow>php prayers-cli prayers:daemon start</>');
        }

        $this->line('');

        // Show next prayer time
        try {
            $prayerTimesService = new PrayerTimesService;
            $timings = $prayerTimesService->getTodayTimings(
                $this->option('city'),
                $this->option('country'),
                $this->option('method'),
            );
            $prayerTimes = new \App\Services\PrayerTimes($timings, $this->option('timezone'));
            $next = $prayerTimes->getNextPrayerName();
            $countdown = $prayerTimes->getNextPrayerCountdown();

            if ($next && $countdown) {
                $this->line("  Next prayer: <fg=green;options=bold>$next</> in $countdown");
            }
        } catch (Exception $e) {
            $this->line('  Next prayer: <fg=yellow>unavailable</> (' . $e->getMessage() . ')');
        }

        $this->line('');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Logs
    // -------------------------------------------------------------------------

    private function logs(): int
    {
        $lines = (int) $this->option('lines');
        $stdoutLog = "/tmp/{$this->label}.stdout.log";
        $stderrLog = "/tmp/{$this->label}.stderr.log";

        $this->line('');
        $this->line('  <fg=cyan>Adhan Daemon Logs</> (last ' . $lines . ' lines)');
        $this->line('  ' . str_repeat('─', 50));
        $this->line('');

        $hasOutput = false;

        if (file_exists($stdoutLog) && filesize($stdoutLog) > 0) {
            $hasOutput = true;
            $this->line('  <fg=green>stdout:</>');
            $this->line('  ' . str_repeat('─', 20));
            passthru("tail -n $lines " . escapeshellarg($stdoutLog));
            $this->line('');
        }

        if (file_exists($stderrLog) && filesize($stderrLog) > 0) {
            $hasOutput = true;
            $this->line('  <fg=yellow>stderr:</>');
            $this->line('  ' . str_repeat('─', 20));
            passthru("tail -n $lines " . escapeshellarg($stderrLog));
            $this->line('');
        }

        if (! $hasOutput) {
            $this->line('  No logs found yet. The daemon may not have run.');
            $this->line('  Check status: <fg=yellow>php prayers-cli prayers:daemon status</>');
        }

        $this->line('');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getLaunchAgentDir(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/';

        return $home . '/Library/LaunchAgents';
    }

    private function getPlistPath(): string
    {
        return $this->getLaunchAgentDir() . "/$this->label.plist";
    }

    private function resolveCliPath(): ?string
    {
        // Running inside a compiled PHAR?
        $pharPath = Phar::running(false);
        if ($pharPath !== '') {
            return realpath($pharPath) ?: $pharPath;
        }

        // Check builds/ first (most common after app:build)
        $buildPath = base_path('builds/prayers-cli');
        if (file_exists($buildPath)) {
            return realpath($buildPath);
        }

        // Check project root
        $rootPath = base_path('prayers-cli');
        if (file_exists($rootPath)) {
            return realpath($rootPath);
        }

        return null;
    }

    private function generatePlist(
        string $cliPath,
        string $city,
        string $country,
        int|string $method,
        string $timezone,
        string $variant,
        string $player,
        int|string $grace,
    ): string {
        $arguments = [
            PHP_BINARY,
            $cliPath,
            'prayers:check',
            "--city=$city",
            "--country=$country",
            "--method=$method",
            "--timezone=$timezone",
            "--variant=$variant",
            "--player=$player",
            "--grace=$grace",
        ];

        $programArguments = '';
        foreach ($arguments as $arg) {
            $escaped = htmlspecialchars((string) $arg, ENT_XML1);
            $programArguments .= "      <string>$escaped</string>\n";
        }

        $plistContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$this->label</string>
    <key>ProgramArguments</key>
    <array>
$programArguments    </array>
    <key>StartInterval</key>
    <integer>60</integer>
    <key>RunAtLoad</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/tmp/{$this->label}.stdout.log</string>
    <key>StandardErrorPath</key>
    <string>/tmp/{$this->label}.stderr.log</string>
    <key>KeepAlive</key>
    <false/>
</dict>
</plist>
XML;

        $tempFile = tempnam(sys_get_temp_dir(), 'plist_') . '.plist';
        file_put_contents($tempFile, $plistContent);

        return $tempFile;
    }
}
