<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSecretCommand extends Command
{
    protected $signature = 'pay-relay:secret
                            {--length=64 : Panjang secret (min 32)}
                            {--show : Hanya tampilkan, jangan tulis .env}
                            {--force : Timpa jika sudah ada}';

    protected $description = 'Generate PAY_RELAY_WEBHOOK_SECRET untuk webhook HMAC';

    public function handle(): int
    {
        $length = (int) $this->option('length');
        if ($length < 32) {
            $this->error('Length minimal 32.');
            return self::FAILURE;
        }

        $secret = Str::random($length);

        if ($this->option('show')) {
            $this->info($secret);
            return self::SUCCESS;
        }

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env tidak ditemukan di ' . $envPath);
            $this->info($secret);

            return self::FAILURE;
        }

        $content = file_get_contents($envPath);

        if (str_contains($content, 'PAY_RELAY_WEBHOOK_SECRET=')) {
            if (!$this->option('force') && !$this->confirm('PAY_RELAY_WEBHOOK_SECRET sudah ada, timpa?', false)) {
                $this->info('Batal.');
                return self::SUCCESS;
            }
            $content = preg_replace('/^PAY_RELAY_WEBHOOK_SECRET=.*/m', "PAY_RELAY_WEBHOOK_SECRET={$secret}", $content);
        } else {
            $content = rtrim($content) . PHP_EOL . "PAY_RELAY_WEBHOOK_SECRET={$secret}" . PHP_EOL;
        }

        file_put_contents($envPath, $content);

        $this->info("PAY_RELAY_WEBHOOK_SECRET={$secret} written to .env");
        $this->warn('Jangan lupa set secret yang sama di aplikasi Android listener!');

        return self::SUCCESS;
    }
}
