<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Redis;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\RedisHelper;

class InstallRedisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:install-redis 
                           {--server : Also install Redis server}
                           {--configure : Configure after installation}
                           {--test : Test after installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Redis server and phpredis extension for ArtFlow Tenancy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Redis Installation for ArtFlow Tenancy');
        $this->newLine();

        if ($this->option('server')) {
            $this->installRedisServer();
            $this->newLine();
        }

        $this->installPhpRedis();

        if ($this->option('configure')) {
            $this->newLine();
            $this->call('tenancy:configure-redis', ['--configure' => true]);
        }

        if ($this->option('test')) {
            $this->newLine();
            $this->call('tenancy:configure-redis', ['--test' => true]);
        }

        $this->newLine();
        $this->info('✅ Redis installation completed!');
        $this->line('💡 Run "php artisan tenancy:configure-redis" to complete setup');

        return 0;
    }

    private function installRedisServer(): void
    {
        $this->info('📦 Installing Redis Server...');

        // Check if Redis is already installed
        $redisCheck = shell_exec('which redis-server 2>/dev/null');
        if (!empty($redisCheck)) {
            $this->line('   • Redis server already installed');
            return;
        }

        $this->line('   • Updating package index...');
        $this->execCommand('sudo apt update');

        $this->line('   • Installing Redis server...');
        $result = $this->execCommand('sudo apt install -y redis-server');

        if ($result === 0) {
            $this->line('   • Starting Redis service...');
            $this->execCommand('sudo systemctl start redis-server');
            $this->execCommand('sudo systemctl enable redis-server');

            $this->line('   • Testing Redis server...');
            $ping = shell_exec('redis-cli ping 2>/dev/null');
            if (trim($ping) === 'PONG') {
                $this->info('   ✅ Redis server installed and running');
            } else {
                $this->warn('   ⚠️ Redis server installed but may not be running properly');
            }
        } else {
            $this->error('   ❌ Failed to install Redis server');
        }
    }

    private function installPhpRedis(): void
    {
        $this->info('📦 Installing phpredis Extension...');

        // Check if extension is already loaded
        if (extension_loaded('redis')) {
            $this->line('   • phpredis already installed (version ' . phpversion('redis') . ')');
            return;
        }

        $this->line('   • Installing build dependencies...');
        $this->execCommand('sudo apt install -y php8.3-dev php-pear build-essential');

        $this->line('   • Installing phpredis via PECL...');
        $output = shell_exec('sudo pecl install redis 2>&1');

        if (str_contains($output, 'successfully') || str_contains($output, 'already installed')) {
            $this->line('   • Creating module configuration...');
            shell_exec('echo "extension=redis.so" | sudo tee /etc/php/8.3/mods-available/redis.ini > /dev/null');

            $this->line('   • Enabling extension...');
            shell_exec('sudo phpenmod redis');

            $this->line('   • Restarting PHP-FPM...');
            shell_exec('sudo systemctl restart php8.3-fpm');

            // Verify installation
            $check = shell_exec('php -m | grep redis');
            if (!empty($check)) {
                $this->info('   ✅ phpredis extension installed successfully');
            } else {
                $this->warn('   ⚠️ phpredis installed but may require web server restart');
            }
        } else {
            $this->error('   ❌ Failed to install phpredis extension');
            $this->line('   Error output: ' . $output);
        }
    }

    protected function execCommand(string $command): int
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ],
            $pipes
        );

        if (is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            return proc_close($process);
        }

        return 1;
    }
}
