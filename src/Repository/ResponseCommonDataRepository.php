<?php

namespace App\Repository;

use App\Entity\ResponseCommonData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponseCommonData>
 *
 * @method ResponseCommonData|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResponseCommonData|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResponseCommonData[]    findAll()
 * @method ResponseCommonData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResponseCommonDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponseCommonData::class);
    }

    public function save(ResponseCommonData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ResponseCommonData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findExpiredSessions(): array
    {
        $queryBuilder = $this->createQueryBuilder('rcd')
            ->select('DISTINCT rcd.sessionId')
            ->where('rcd.last_activity < :expirationTime')
            ->setParameter('expirationTime', new \DateTime('-3600 seconds'))
            ->getQuery();

        $expiredResponsesIds = $queryBuilder->getResult();

        return $expiredResponsesIds;
    }

//    /**
//     * @return ResponseCommonData[] Returns an array of ResponseCommonData objects
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

//    public function findOneBySomeField($value): ?ResponseCommonData
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
