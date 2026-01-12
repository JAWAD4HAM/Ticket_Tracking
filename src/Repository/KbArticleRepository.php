<?php

namespace App\Repository;

use App\Entity\KbArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KbArticle>
 *
 * @method KbArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method KbArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method KbArticle[]    findAll()
 * @method KbArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KbArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KbArticle::class);
    }

    public function save(KbArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(KbArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return KbArticle[] Returns an array of published KbArticle objects
     */
    public function findAllPublished(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.isPublished = :val')
            ->setParameter('val', true)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search articles by keyword and optional category
     * Only returns published articles for clients
     */
    public function searchByKeywordAndCategory(?string $keyword, ?int $categoryId): array
    {
        $qb = $this->createQueryBuilder('k')
            ->andWhere('k.isPublished = :published')
            ->setParameter('published', true);

        if ($keyword) {
            $qb->andWhere('LOWER(k.title) LIKE LOWER(:keyword) OR LOWER(k.content) LIKE LOWER(:keyword)')
               ->setParameter('keyword', '%' . $keyword . '%');
        }

        if ($categoryId) {
            $qb->andWhere('k.category = :category')
               ->setParameter('category', $categoryId);
        }

        return $qb->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
