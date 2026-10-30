<?php

declare(strict_types=1);

namespace App\Contact;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class ContactMailer
{
    private const string SUBJECT = 'Nieuw bericht via het contactformulier';

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%mail.from%')] private string $from,
        #[Autowire('%scan.contact_email%')] private string $to,
    ) {
    }

    public function send(string $name, string $email, string $message): bool
    {
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(Address::create($this->from))
                ->to($this->to)
                ->replyTo($email)
                ->subject(self::SUBJECT)
                ->htmlTemplate('email/contact_message.html.twig')
                ->context(['visitorName' => $name, 'visitorEmail' => $email, 'text' => $message]));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Contactbericht kon niet worden verstuurd.', ['exception' => $e]);

            return false;
        }

        return true;
    }
}
