<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailjetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-mailjet {email : The email address to send the test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using Mailjet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Sending test email to {$email} using Mailjet...");
        
        try {
            Mail::mailer('mailjet')
                ->to($email)
                ->send(new TestMail());
                
            $this->info('Test email sent successfully!');
        } catch (\Exception $e) {
            $this->error("Failed to send test email: {$e->getMessage()}");
        }
    }
}
