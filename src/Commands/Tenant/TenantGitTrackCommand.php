<?php

namespace ArtflowStudio\Tenancy\Commands\Tenant;

/**
 * Tenant Asset Git Tracker
 *
 * This command helps you easily track or untrack tenant public assets in git
 *
 * Usage:
 *   php artisan tenant:git:track {domain} [--type=all|assets|pwa|seo]
 *   php artisan tenant:git:untrack {domain}
 */

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TenantGitTrackCommand extends Command
{
    protected $signature = 'tenant:git:track 
                            {domain : The tenant domain to track (e.g., al-emaan.pk)}
                            {--type=all : What to track: all, assets, pwa, seo}
                            {--untrack : Untrack instead of track}';

    protected $description = 'Track or untrack tenant public assets in git';

    public function handle()
    {
        $domain = $this->argument('domain');
        $type = $this->option('type');
        $untrack = $this->option('untrack');

        $gitignorePath = base_path('storage/app/public/tenants/.gitignore');

        if (! File::exists($gitignorePath)) {
            $this->error('.gitignore not found in storage/app/public/tenants/');

            return 1;
        }

        $content = File::get($gitignorePath);

        if ($untrack) {
            // Remove tracking rules
            $this->untrackTenant($content, $domain, $gitignorePath);
        } else {
            // Add tracking rules
            $this->trackTenant($content, $domain, $type, $gitignorePath);
        }

        return 0;
    }

    protected function trackTenant($content, $domain, $type, $gitignorePath)
    {
        $rules = "\n# Production tenant: {$domain}\n";

        switch ($type) {
            case 'all':
                $rules .= "!{$domain}/\n";
                $rules .= "!{$domain}/**\n";
                break;

            case 'assets':
                $rules .= "!{$domain}/\n";
                $rules .= "!{$domain}/assets/\n";
                $rules .= "!{$domain}/assets/**\n";
                break;

            case 'pwa':
                $rules .= "!{$domain}/\n";
                $rules .= "!{$domain}/pwa/\n";
                $rules .= "!{$domain}/pwa/**\n";
                break;

            case 'seo':
                $rules .= "!{$domain}/\n";
                $rules .= "!{$domain}/seo/\n";
                $rules .= "!{$domain}/seo/**\n";
                break;
        }

        // Check if already tracked
        if (strpos($content, "!{$domain}/") !== false) {
            $this->warn("Tenant {$domain} is already being tracked.");
            if (! $this->confirm('Update tracking rules?', false)) {
                return;
            }
            // Remove old rules first
            $content = preg_replace("/\n# Production tenant: {$domain}\n.*?\n.*?\n/s", '', $content);
        }

        $content .= $rules;
        File::put($gitignorePath, $content);

        $this->info("✓ Added git tracking rules for: {$domain} (type: {$type})");
        $this->line("\nNext steps:");
        $this->line("1. git add storage/app/public/tenants/{$domain}/");
        $this->line('2. git add storage/app/public/tenants/.gitignore');
        $this->line("3. git commit -m 'Track tenant assets for {$domain}'");
    }

    protected function untrackTenant($content, $domain, $gitignorePath)
    {
        // Remove tracking rules for this domain
        $pattern = "/\n# Production tenant: {$domain}\n.*?\n.*?\n/s";
        $newContent = preg_replace($pattern, '', $content);

        if ($newContent === $content) {
            $this->warn("Tenant {$domain} is not currently tracked.");

            return;
        }

        File::put($gitignorePath, $newContent);

        $this->info("✓ Removed git tracking rules for: {$domain}");
        $this->line("\nNext steps:");
        $this->line("1. git rm -r --cached storage/app/public/tenants/{$domain}/");
        $this->line('2. git add storage/app/public/tenants/.gitignore');
        $this->line("3. git commit -m 'Untrack tenant assets for {$domain}'");
    }
}
