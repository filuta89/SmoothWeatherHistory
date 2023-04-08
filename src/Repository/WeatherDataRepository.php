<?php

namespace App\Repository;

use App\Entity\WeatherData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<WeatherData>
 *
 * @method WeatherData|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherData|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherData[]    findAll()
 * @method WeatherData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherData::class);
    }

    public function save(WeatherData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WeatherData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

/*    public function findExpiredSessions(CacheItemPoolInterface $cache): array
    {
        $queryBuilder = $this->createQueryBuilder('wd')
            ->select('DISTINCT wd.sessionId')
            ->where('wd.last_activity < :expirationTime')
            ->setParameter('expirationTime', new \DateTime('-3600 seconds'))
            ->getQuery();

        $sessionIds = $queryBuilder->getResult();
        $expiredSessionIds = [];

        foreach ($sessionIds as $id) {
            $sessionId = $id['sessionId'];
            $cacheKey = 'session_' . $sessionId;
            $cacheItem = $cache->getItem($cacheKey);

            if (!$cacheItem->isHit()) {
                $expiredSessionIds[] = $sessionId;
            }
        }

        // Print the list of expired session IDs to a file
        //file_put_contents('expired_sessions.log', implode(PHP_EOL, $expiredSessionIds));

        return $expiredSessionIds;
    }*/

//    /**
//     * @return WeatherData[] Returns an array of WeatherData objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?WeatherData
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
