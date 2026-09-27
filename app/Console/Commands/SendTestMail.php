<?php

namespace App\Console\Commands;

use App\Support\AppUrl;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('app:mail-test {to : Address to send the test to}')]
#[Description('Send a test mail right away (not via the queue) to check the SMTP settings')]
class SendTestMail extends Command
{
    public function handle(): int
    {
        $config = config('mail.mailers.'.config('mail.default'));

        $this->components->twoColumnDetail('Mailer', config('mail.default'));
        $this->components->twoColumnDetail('Host', ($config['scheme'] ?? '').'://'.($config['host'] ?? '—').':'.($config['port'] ?? '—'));
        $this->components->twoColumnDetail('Username', $config['username'] ?? '—');
        $this->components->twoColumnDetail('From', config('mail.from.address') ?? '—');

        if (($config['username'] ?? null) && config('mail.from.address') !== $config['username']) {
            $this->components->warn('From differs from the SMTP username. OVH only lets a mailbox send as itself — expect a rejection.');
        }

        $this->components->twoColumnDetail('Links in mails', route('portal.login'));

        if (AppUrl::isLocal(config('app.url'))) {
            $this->components->warn('Links in mails point at this machine. Set APP_URL to the public URL, or a domain on the app service in Coolify.');
        }

        try {
            Mail::raw('Testbericht van '.config('app.name').'. Als je dit leest, werkt het versturen van e-mails.', function ($message) {
                $message->to($this->argument('to'))->subject('Testbericht — '.config('app.name'));
            });
        } catch (Throwable $e) {
            $this->components->error('Versturen mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Verstuurd naar '.$this->argument('to').'. Kijk ook in de spamfolder.');

        return self::SUCCESS;
    }
}
