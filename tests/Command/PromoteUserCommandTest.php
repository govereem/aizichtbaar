<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PromoteUserCommandTest extends AppWebTestCase
{
    public function testPromoteGivesUserAdminRole(): void
    {
        $this->createUser('klant@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $application = new Application($this->client->getKernel());
        $tester = new CommandTester($application->find('app:user:promote'));
        $tester->execute(['email' => 'klant@example.com']);

        $tester->assertCommandIsSuccessful();
        $this->assertContains('ROLE_ADMIN', $this->userByEmail('klant@example.com')->getRoles());
    }

    public function testPromoteUnknownUserFails(): void
    {
        $application = new Application($this->client->getKernel());
        $tester = new CommandTester($application->find('app:user:promote'));
        $tester->execute(['email' => 'niemand@example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
