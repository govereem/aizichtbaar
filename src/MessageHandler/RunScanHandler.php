<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Scan;
use App\Message\PublishSiteMessage;
use App\Message\RunScanMessage;
use App\Scan\ExtendedScanRunner;
use App\Scan\ScanReportMailer;
use App\Scan\ScanRunner;
use App\Scan\SiteUnreachableException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RunScanHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ScanRunner $runner,
        private ExtendedScanRunner $extendedRunner,
        private ScanReportMailer $mailer,
        private LockFactory $locks,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunScanMessage $message): void
    {
        // Lock before reading the row, so two deliveries of the same message cannot both pass the status check.
        $lock = $this->locks->createLock('run-scan-'.$message->scanId);
        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->runScan($message->scanId);
        } finally {
            $lock->release();
        }
    }

    private function runScan(int $scanId): void
    {
        $scan = $this->em->find(Scan::class, $scanId);

        // Only scans that are waiting may start. A redelivered message must not re-run a finished scan.
        if (null === $scan || !in_array($scan->getStatus(), [Scan::STATUS_QUEUED, Scan::STATUS_PAID], true)) {
            return;
        }

        $scan->setStatus(Scan::STATUS_RUNNING);
        $this->em->flush();

        try {
            $scan->setResults($this->results($scan));
            $scan->setStatus(Scan::STATUS_COMPLETED);
        } catch (SiteUnreachableException $e) {
            $scan->setStatus(Scan::STATUS_FAILED);
            $scan->setFailureReason($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Scan {id} failed.', ['id' => $scan->getId(), 'exception' => $e]);
            $scan->setStatus(Scan::STATUS_FAILED);
            $scan->setFailureReason('De site kon niet worden gescand. Controleer de URL en probeer het opnieuw.');
        }

        $this->em->flush();

        if (Scan::STATUS_COMPLETED === $scan->getStatus() && 'paid' === $scan->getType()) {
            $this->mailer->send($scan);
        }

        // Anonymous free scans may become a public page. That runs in its own message, so the result is not held up.
        if (Scan::STATUS_COMPLETED === $scan->getStatus() && 'free' === $scan->getType() && null === $scan->getUser()) {
            $this->bus->dispatch(new PublishSiteMessage($scan->getId()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function results(Scan $scan): array
    {
        if ('paid' === $scan->getType()) {
            $results = $this->extendedRunner->run($scan->getWebsiteUrl(), $scan->getCompetitorUrls() ?? []);
            if ($results['degraded']) {
                $this->logger->warning('Uitgebreide scan {id} draaide zonder volledige AI-analyse.', ['id' => $scan->getId(), 'steps' => $results['degraded_steps']]);
            }

            return $results;
        }

        return $this->runner->run($scan->getWebsiteUrl());
    }
}
