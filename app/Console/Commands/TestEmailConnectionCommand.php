<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\Facades\Client;

class TestEmailConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check {--send-to= : Recipient email address to test sending a live SMTP email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test IMAP and SMTP configuration and network connectivity for Render/Production';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('     HELP-DESK IMAP & SMTP DIAGNOSTICS CHECK        ');
        $this->info('====================================================');

        $hasError = false;

        // 1. CHECK IMAP CONFIGURATION & CONNECTION
        $this->info("\n[1/2] Checking IMAP Configuration & Connection...");
        $imapHost = config('imap.accounts.default.host');
        $imapPort = config('imap.accounts.default.port');
        $imapUser = config('imap.accounts.default.username');
        $imapPass = config('imap.accounts.default.password');
        $imapEnc  = config('imap.accounts.default.encryption');

        $this->line(" - IMAP Host:       <comment>{$imapHost}:{$imapPort}</comment>");
        $this->line(" - IMAP Encryption: <comment>{$imapEnc}</comment>");
        $this->line(" - IMAP Username:   <comment>" . ($imapUser ?: '<NOT SET>') . "</comment>");
        $this->line(" - IMAP Password:   <comment>" . ($imapPass ? '****** (configured)' : '<NOT SET>') . "</comment>");

        if (empty($imapUser) || empty($imapPass)) {
            $this->error(" ✖ IMAP FAIL: Missing credentials. Set IMAP_USERNAME / MAIL_USERNAME & IMAP_PASSWORD / MAIL_PASSWORD in environment variables.");
            $hasError = true;
        } else {
            try {
                $client = Client::account('default');
                $client->connect();
                $folder = $client->getFolder('INBOX');
                $unreadCount = $folder->query()->unseen()->count();
                $this->info(" ✔ IMAP SUCCESS: Connected to {$imapHost}! Unread INBOX emails: {$unreadCount}");
            } catch (\Throwable $e) {
                $this->error(" ✖ IMAP FAIL: " . $e->getMessage());
                $this->warn("   Troubleshooting:");
                $this->warn("   1. Verify your credentials (App Password if using Gmail, 2-Step Verification enabled).");
                $this->warn("   2. Ensure IMAP access is enabled in your email provider settings.");
                $this->warn("   3. Check Environment Variables: IMAP_HOST=imap.gmail.com, IMAP_PORT=993, IMAP_ENCRYPTION=ssl");
                $hasError = true;
            }
        }

        // 2. CHECK SMTP CONFIGURATION & CONNECTION
        $this->info("\n[2/2] Checking SMTP Configuration & Connection...");
        $smtpHost = config('mail.mailers.smtp.host');
        $smtpPort = config('mail.mailers.smtp.port');
        $smtpUser = config('mail.mailers.smtp.username');
        $smtpPass = config('mail.mailers.smtp.password');
        $smtpEnc  = config('mail.mailers.smtp.encryption');
        $fromAddr = config('mail.from.address');

        $this->line(" - SMTP Host:       <comment>{$smtpHost}:{$smtpPort}</comment>");
        $this->line(" - SMTP Encryption: <comment>{$smtpEnc}</comment>");
        $this->line(" - SMTP Username:   <comment>" . ($smtpUser ?: '<NOT SET>') . "</comment>");
        $this->line(" - SMTP Password:   <comment>" . ($smtpPass ? '****** (configured)' : '<NOT SET>') . "</comment>");
        $this->line(" - From Address:    <comment>{$fromAddr}</comment>");

        if (empty($smtpUser) || empty($smtpPass)) {
            $this->error(" ✖ SMTP FAIL: Missing credentials. Set MAIL_USERNAME and MAIL_PASSWORD in environment variables.");
            $hasError = true;
        } else {
            $fp = @fsockopen($smtpHost, (int) $smtpPort, $errno, $errstr, 5);
            if (!$fp) {
                $this->error(" ✖ SMTP FAIL: Cannot reach host {$smtpHost}:{$smtpPort} ({$errstr})");
                $hasError = true;
            } else {
                fclose($fp);
                $this->info(" ✔ SMTP Socket: Host {$smtpHost}:{$smtpPort} is reachable.");

                $recipient = $this->option('send-to');
                if ($recipient) {
                    $this->info(" Attempting to send test email to <{$recipient}>...");
                    try {
                        Mail::raw("This is a test email from AI-Helpdesk system verification on " . date('Y-m-d H:i:s'), function ($msg) use ($recipient, $fromAddr) {
                            $msg->to($recipient)
                                ->from($fromAddr)
                                ->subject('AI Helpdesk SMTP Diagnostic Test');
                        });
                        $this->info(" ✔ SMTP SUCCESS: Test email sent successfully to <{$recipient}>!");
                    } catch (\Throwable $e) {
                        $this->error(" ✖ SMTP FAIL: Failed to send email: " . $e->getMessage());
                        $hasError = true;
                    }
                } else {
                    $this->info(" ✔ SMTP Connection OK. Pass --send-to=your-email@example.com to send a live test message.");
                }
            }
        }

        $this->info("\n====================================================");
        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
