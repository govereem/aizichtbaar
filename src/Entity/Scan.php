<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Scan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $websiteUrl = null;

    #[ORM\Column(length: 20)]
    private string $type = 'free'; // free or paid

    #[ORM\Column(length: 50)]
    private string $status = 'pending'; // pending, paid, completed

    #[ORM\Column(nullable: true)]
    private ?string $molliePaymentId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $results = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
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
}
