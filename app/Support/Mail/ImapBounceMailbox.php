<?php

namespace App\Support\Mail;

use Closure;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

/**
 * The no-reply mailbox on Hostinger, read over IMAP.
 *
 * Bounces for mail sent through Hostinger's relay (MailChannels) come back to
 * the sender address as emails. People have been reading and binning them, so
 * the trash is searched as well as the inbox. A processed report is moved to
 * its own folder rather than flagged: this mailbox does not allow custom IMAP
 * keywords, and moving it also keeps the evidence out of the trash.
 */
final class ImapBounceMailbox implements BounceMailbox
{
    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string, folders: list<string>, processed_folder: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function process(Closure $handle): int
    {
        $client = (new ClientManager(['options' => ['sequence' => IMAP::ST_UID]]))->make([
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'encryption' => $this->config['encryption'],
            'validate_cert' => true,
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'protocol' => 'imap',
        ]);
        $client->connect();

        $target = $this->config['processed_folder'];
        $filed = 0;
        try {
            foreach ($this->config['folders'] as $path) {
                $folder = $client->getFolderByPath($path);
                if ($folder === null || $path === $target) {
                    continue;
                }

                foreach ($folder->query()->from('mailer-daemon')->leaveUnread()->get() as $message) {
                    if (! $handle($message->getHeader()->raw."\r\n\r\n".$message->getRawBody())) {
                        continue;
                    }

                    // Created on first use, so a run with nothing to file changes nothing.
                    if ($client->getFolderByPath($target) === null) {
                        $client->createFolder($target, false);
                    }
                    $message->move($target, true);
                    $filed++;
                }
            }
        } finally {
            $client->disconnect();
        }

        return $filed;
    }
}
