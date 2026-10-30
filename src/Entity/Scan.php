<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Scan
{
    public const STATUS_PENDING = 'pending';     // paid scan, waiting for Mollie
    public const STATUS_PAID = 'paid';           // paid scan, not started yet
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Unguessable token for the result URL. Sequential ids would let anyone read every scan.
    #[ORM\Column(length: 32, unique: true)]
    private string $publicId;

    #[ORM\Column(length: 255)]
    private ?string $websiteUrl = null;

    #[ORM\Column(length: 20)]
    private string $type = 'free'; // free or paid

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?string $molliePaymentId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $results = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\ManyToOne(inversedBy: 'scans')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    // Paid scans only: up to three competitor homepages to compare against.
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $competitorUrls = null;

    // Where the report is sent. Anonymous buyers have no account, so the address is stored on the scan.
    #[ORM\Column(nullable: true)]
    private ?string $email = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->publicId = bin2hex(random_bytes(16));
    }

    public function getId(): ?int { return $this->id; }
    public function getPublicId(): string { return $this->publicId; }
    public function getFailureReason(): ?string { return $this->failureReason; }
    public function setFailureReason(?string $reason): static { $this->failureReason = $reason; return $this; }
    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function setWebsiteUrl(string $url): static { $this->websiteUrl = $url; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getMolliePaymentId(): ?string { return $this->molliePaymentId; }
    public function setMolliePaymentId(?string $id): static { $this->molliePaymentId = $id; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getResults(): ?array { return $this->results; }
    public function setResults(?array $results): static { $this->results = $results; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getCompetitorUrls(): ?array { return $this->competitorUrls; }
    public function setCompetitorUrls(?array $urls): static { $this->competitorUrls = $urls; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
}
