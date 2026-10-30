<?php

declare(strict_types=1);

namespace App\Service;

use Mollie\Api\MollieApiClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MollieService
{
    private MollieApiClient $mollie;

    public function __construct(ParameterBagInterface $params)
    {
        $this->mollie = new MollieApiClient();
        $apiKey = $params->get('mollie.api_key') ?? 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $this->mollie->setApiKey($apiKey);
    }

    public function createPayment(string $amount, string $description, string $redirectUrl, string $webhookUrl, array $metadata = []): object
    {
        return $this->mollie->payments->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => $amount, // e.g. '49.00'
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => $metadata,
        ]);
    }

    public function getPayment(string $paymentId): object
    {
        return $this->mollie->payments->get($paymentId);
    }
}
