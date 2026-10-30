<?php

declare(strict_types=1);

namespace App\Scan;

use App\Entity\Scan;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class ScanReportMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%mail.from%')] private string $from,
    ) {
    }

    /**
     * Sends the link to a finished paid scan. A mail failure must not turn a finished scan into a failed one.
     */
    public function send(Scan $scan): void
    {
        $to = $scan->getEmail() ?? $scan->getUser()?->getEmail();
        if (null === $to) {
            return;
        }

        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(Address::create($this->from))
                ->to($to)
                ->subject('Je uitgebreide AI-scan is klaar')
                ->htmlTemplate('email/scan_report.html.twig')
                ->context(['scan' => $scan]));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Scanrapport kon niet worden verstuurd.', ['scanId' => $scan->getId(), 'exception' => $e]);
        }
    }
}
