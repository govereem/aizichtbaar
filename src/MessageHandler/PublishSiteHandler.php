<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Scan;
use App\Message\PublishSiteMessage;
use App\Scan\Publish\PublicSitePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PublishSiteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private PublicSitePublisher $publisher,
    ) {
    }

    public function __invoke(PublishSiteMessage $message): void
    {
        $scan = $this->em->find(Scan::class, $message->scanId);

        // Only finished anonymous free scans are published. Account scans stay private.
        if (null === $scan || Scan::STATUS_COMPLETED !== $scan->getStatus() || 'free' !== $scan->getType() || null !== $scan->getUser()) {
            return;
        }

        $this->publisher->publish($scan);
    }
}
