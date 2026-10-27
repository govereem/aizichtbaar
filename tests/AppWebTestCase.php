<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AppWebTestCase extends WebTestCase
{
    use MailerAssertionsTrait;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = $this->entityManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email, string $password, bool $verified = true, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsVerified($verified);
        $user->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    protected function userByEmail(string $email): ?User
    {
        return $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Returns the first URL in the last sent email that matches the pattern.
     */
    protected function urlFromLastEmail(string $pattern): string
    {
        $message = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $message);

        preg_match($pattern, (string) $message->getHtmlBody(), $matches);
        $this->assertNotEmpty($matches, 'No matching link found in the email.');

        return html_entity_decode($matches[0]);
    }
}
