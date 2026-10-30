<?php

declare(strict_types=1);

namespace App\Tests\Scan\Publish;

use App\Scan\Publish\PublicSiteGate;
use PHPUnit\Framework\TestCase;

class PublicSiteGateTest extends TestCase
{
    public function testOnlyAllowedTldsGetAPublicDomain(): void
    {
        $gate = new PublicSiteGate(['nl', 'de', 'be'], []);

        $this->assertSame('voorbeeld.nl', $gate->domainFor('https://www.Voorbeeld.nl/diensten'));
        $this->assertSame('voorbeeld.de', $gate->domainFor('https://voorbeeld.de'));
        $this->assertNull($gate->domainFor('https://voorbeeld.com'));
    }

    public function testIpAddressesAndSingleLabelHostsAreRejected(): void
    {
        $gate = new PublicSiteGate(['nl', 'de', 'be'], []);

        $this->assertNull($gate->domainFor('http://127.0.0.1'));
        $this->assertNull($gate->domainFor('http://localhost'));
    }

    public function testBlocklistedHostsAreRejected(): void
    {
        $gate = new PublicSiteGate(['nl'], ['spam.nl']);

        $this->assertNull($gate->domainFor('https://spam.nl'));
        $this->assertSame('goed.nl', $gate->domainFor('https://goed.nl'));
    }

    public function testParkedSitesWithLittleTextAreRejected(): void
    {
        $gate = new PublicSiteGate(['nl'], []);

        $this->assertFalse($gate->hasEnoughText('<html><body><p>Kort.</p></body></html>'));
        $this->assertTrue($gate->hasEnoughText('<html><body><p>'.str_repeat('woord ', PublicSiteGate::MIN_WORDS).'</p></body></html>'));
    }

    public function testNoindexOrDisallowAllMeansStayOut(): void
    {
        $gate = new PublicSiteGate(['nl'], []);

        $this->assertTrue($gate->asksToStayOut('<html><head><meta name="robots" content="NoIndex, follow"></head></html>', null));
        $this->assertTrue($gate->asksToStayOut('<html></html>', "User-agent: *\nDisallow: /\n"));
        $this->assertFalse($gate->asksToStayOut('<html></html>', "User-agent: *\nDisallow: /admin\n"));
        $this->assertTrue($gate->asksToStayOut('<html></html>', null, 'noindex'));
        $this->assertFalse($gate->asksToStayOut('<html></html>', null));
    }
}
