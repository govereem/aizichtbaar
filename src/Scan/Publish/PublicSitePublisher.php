<?php

declare(strict_types=1);

namespace App\Scan\Publish;

use App\Ai\AiUnavailableException;
use App\Entity\PublicSite;
use App\Entity\Scan;
use App\Scan\Ai\SiteSummarizer;
use App\Scan\SiteFetcher;
use App\Scan\SiteMeta;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Turns a completed anonymous scan into a row in the public directory, when the gates let it through.
 */
final readonly class PublicSitePublisher
{
    private const RESCAN_AFTER = '-7 days';

    public function __construct(
        private EntityManagerInterface $em,
        private SiteFetcher $fetcher,
        private PublicSiteGate $gate,
        private SiteSummarizer $summarizer,
        private LockFactory $locks,
        #[Autowire('%scan.indexing_mode%')]
        private string $indexingMode,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(Scan $scan): void
    {
        $domain = $this->gate->domainFor((string) $scan->getWebsiteUrl());
        if (null === $domain) {
            return;
        }

        // Two scans of one domain must not both create the row.
        $lock = $this->locks->createLock('publish-site-'.$domain);
        $lock->acquire(true);

        try {
            $this->publishDomain($scan, $domain);
        } finally {
            $lock->release();
        }
    }

    private function publishDomain(Scan $scan, string $domain): void
    {
        $existing = $this->em->getRepository(PublicSite::class)->findOneBy(['domain' => $domain]);

        // A removed site stays removed. A site scanned in the last week is not fetched and summarised again.
        if (null !== $existing && (PublicSite::STATUS_REMOVED === $existing->getStatus() || $existing->getScannedAt() > new \DateTimeImmutable(self::RESCAN_AFTER))) {
            return;
        }

        $site = $this->fetcher->fetch((string) $scan->getWebsiteUrl());

        // A site that redirects to another domain is not published under the name that was scanned.
        $finalDomain = $this->gate->domainFor($site->finalUrl ?? (string) $scan->getWebsiteUrl());
        if (null === $site->homepage || $finalDomain !== $domain || !$this->gate->hasEnoughText($site->homepage) || $this->gate->asksToStayOut($site->homepage, $site->robotsTxt, $site->xRobotsTag)) {
            return;
        }

        try {
            $profile = $this->summarizer->summarize($site->homepage);
        } catch (AiUnavailableException $e) {
            // Soft gate: the page is kept without a description, so it makes no claim about the company.
            $this->logger->warning('Samenvatting voor {domain} mislukt.', ['domain' => $domain, 'exception' => $e]);
            $profile = null;
        }

        // Hard gate: a site the model marks as unsuitable gets no page at all.
        if (null !== $profile && !$profile['safe']) {
            return;
        }

        $results = $scan->getResults() ?? [];
        $publicSite = $existing ?? new PublicSite($domain, $this->slug($domain));
        $publicSite
            ->setName(mb_substr(SiteMeta::fromHtml($site->homepage)->name($domain), 0, 255))
            ->setSummary($profile['summary'] ?? null)
            ->setNiche($profile['niche'] ?? null)
            ->setScore((int) ($results['score'] ?? 0))
            ->setChecks(array_map(
                fn (array $check): array => ['id' => $check['id'], 'title' => $check['title'], 'status' => $check['status']],
                $results['checks'] ?? [],
            ))
            ->setScannedAt(new \DateTimeImmutable());

        if (null === $existing) {
            // Admins decide on new pages when the mode is approval. Existing pages keep the status an admin gave them.
            $publicSite->setStatus(null !== $profile && 'auto' === $this->indexingMode ? PublicSite::STATUS_INDEXABLE : PublicSite::STATUS_NOINDEX);
            $this->em->persist($publicSite);
        }

        $this->em->flush();
    }

    /**
     * Dots become single dashes and dashes become double dashes, so "a.b.nl" and "a-b.nl" never share a slug.
     */
    private function slug(string $domain): string
    {
        return str_replace('.', '-', str_replace('-', '--', $domain));
    }
}
