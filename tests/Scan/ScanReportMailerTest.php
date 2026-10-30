<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Entity\Scan;
use App\Scan\ScanReportMailer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

class ScanReportMailerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testReportIsSentFromTheConfiguredAddress(): void
    {
        self::bootKernel();

        $scan = (new Scan())
            ->setWebsiteUrl('https://example.nl')
            ->setType('paid')
            ->setStatus('done')
            ->setEmail('klant@example.nl');

        self::getContainer()->get(ScanReportMailer::class)->send($scan);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'from', 'noreply@aizichtbaar.nl');
        self::assertEmailAddressContains($email, 'to', 'klant@example.nl');
    }
}
