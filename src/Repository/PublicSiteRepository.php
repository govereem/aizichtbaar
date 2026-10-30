<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PublicSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicSite>
 */
class PublicSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicSite::class);
    }

    /**
     * Only indexable sites: noindex and removed sites never appear in the directory.
     *
     * @return Paginator<PublicSite>
     */
    public function findIndexablePage(int $page, int $perPage): Paginator
    {
        $query = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', PublicSite::STATUS_INDEXABLE)
            ->orderBy('s.domain', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query);
    }
}
