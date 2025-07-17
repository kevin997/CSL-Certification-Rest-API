<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Facades\Log;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class PHPMailerTransport extends AbstractTransport
{
    protected $phpMailer;
    protected $config;

    public function __construct(array $config = [], EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);
        $this->config = $config;
        $this->phpMailer = new PHPMailer(true);
        $this->configurePHPMailer();
    }

    /**
     * Configure PHPMailer with settings from config
     */
    protected function configurePHPMailer()
    {
        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = $this->config['host'] ?? 'localhost';
        $this->phpMailer->SMTPAuth = $this->config['auth'] ?? true;
        $this->phpMailer->Username = $this->config['username'] ?? '';
        $this->phpMailer->Password = $this->config['password'] ?? '';
        
        // Handle encryption
        $encryption = $this->config['encryption'] ?? 'tls';
        if ($encryption === 'ssl') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $this->phpMailer->Port = $this->config['port'] ?? 587;
        $this->phpMailer->CharSet = $this->config['charset'] ?? 'UTF-8';
        
        // Optional: Set timeout
        if (isset($this->config['timeout'])) {
            $this->phpMailer->Timeout = $this->config['timeout'];
        }
        
        // Optional: Set debug level
        if (isset($this->config['debug'])) {
            $this->phpMailer->SMTPDebug = $this->config['debug'];
        }
    }

    /**
     * Send the given message.
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            // Clear any previous recipients
            $this->phpMailer->clearAllRecipients();
            $this->phpMailer->clearAttachments();
            $this->phpMailer->clearCustomHeaders();

            // Set sender
            $from = $email->getFrom();
            if ($from && count($from) > 0) {
                $fromAddress = $from[0];
                $this->phpMailer->setFrom($fromAddress->getAddress(), $fromAddress->getName());
            }

            // Set recipients
            foreach ($email->getTo() as $address) {
                $this->phpMailer->addAddress($address->getAddress(), $address->getName());
            }

            // CC recipients
            foreach ($email->getCc() as $address) {
                $this->phpMailer->addCC($address->getAddress(), $address->getName());
            }

            // BCC recipients
            foreach ($email->getBcc() as $address) {
                $this->phpMailer->addBCC($address->getAddress(), $address->getName());
            }

            // Reply-To
            foreach ($email->getReplyTo() as $address) {
                $this->phpMailer->addReplyTo($address->getAddress(), $address->getName());
            }

            // Subject
            $this->phpMailer->Subject = $email->getSubject();

            // Body
            $body = $email->getHtmlBody();
            if ($body) {
                $this->phpMailer->isHTML(true);
                $this->phpMailer->Body = $body;
                
                // Check for text alternative
                $textBody = $email->getTextBody();
                if ($textBody) {
                    $this->phpMailer->AltBody = $textBody;
                }
            } else {
                $this->phpMailer->isHTML(false);
                $this->phpMailer->Body = $email->getTextBody();
            }

            // Attachments
            foreach ($email->getAttachments() as $attachment) {
                $this->phpMailer->addStringAttachment(
                    $attachment->getBody(),
                    $attachment->getName() ?? 'attachment',
                    PHPMailer::ENCODING_BASE64,
                    $attachment->getContentType()
                );
            }

            // Send the email
            $this->phpMailer->send();

        } catch (PHPMailerException $e) {
            Log::error('PHPMailer transport error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'phpmailer';
    }
}