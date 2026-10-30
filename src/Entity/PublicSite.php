<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PublicSiteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A scanned website shown in the public directory. One row per domain: a rescan updates the row.
 */
#[ORM\Entity(repositoryClass: PublicSiteRepository::class)]
class PublicSite
{
    public const STATUS_INDEXABLE = 'indexable'; // in the directory, sitemap and search engines
    public const STATUS_NOINDEX = 'noindex';     // reachable by link, but search engines are told to stay out
    public const STATUS_REMOVED = 'removed';     // taken down by an admin or on request; gone, and never republished

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191, unique: true)]
    private string $domain;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $name = '';

    // AI-written and checked against the homepage. Null when the AI step failed, so the page makes no claim about the company.
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $niche = null;

    #[ORM\Column]
    private int $score = 0;

    // The check results as shown on the scan: id, title and status only.
    #[ORM\Column(type: 'json')]
    private array $checks = [];

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_NOINDEX;

    #[ORM\Column]
    private \DateTimeImmutable $scannedAt;

    public function __construct(string $domain, string $slug)
    {
        $this->domain = $domain;
        $this->slug = $slug;
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDomain(): string { return $this->domain; }
    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): static { $this->summary = $summary; return $this; }
    public function getNiche(): ?string { return $this->niche; }
    public function setNiche(?string $niche): static { $this->niche = $niche; return $this; }
    public function getScore(): int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }
    public function getChecks(): array { return $this->checks; }
    public function setChecks(array $checks): static { $this->checks = $checks; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getScannedAt(): \DateTimeImmutable { return $this->scannedAt; }
    public function setScannedAt(\DateTimeImmutable $scannedAt): static { $this->scannedAt = $scannedAt; return $this; }
}
