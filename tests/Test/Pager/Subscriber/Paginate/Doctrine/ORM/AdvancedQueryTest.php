<?php

namespace Test\Pager\Subscriber\Paginate\Doctrine\ORM;

use Knp\Component\Pager\ParametersResolver;
use Test\Tool\BaseTestCaseORM;
use Knp\Component\Pager\Paginator;
use Test\Fixture\Entity\Shop\Product;
use Test\Fixture\Entity\Shop\Tag;
use Doctrine\ORM\Query;

class AdvancedQueryTest extends BaseTestCaseORM
{
    /**
     * Its not possible to make distinction and predict
     * count of such query
     *
     * @test
     * @expectedException \RuntimeException
     */
    function shouldFailToPaginateMultiRootQuery()
    {
        $this->populate();

        $dql = <<<SQL
    SELECT p FROM
      Test\Fixture\Entity\Shop\Product p,
      Test\Fixture\Entity\Shop\Tag t
SQL;
        $q = $this->em->createQuery($dql);

        $parametersResolver = $this->createMock(ParametersResolver::class);

        $paginator = new Paginator($parametersResolver);
        $this->startQueryLog();

        $paginator->paginate($q, 1, 2);
    }

    /**
     * @test
     */
    function shouldBeAbleToPaginateWithHavingClause()
    {
        $this->populate();

        $dql = <<<SQL
        SELECT p, t
        FROM Test\Fixture\Entity\Shop\Product p
        INNER JOIN p.tags t
        GROUP BY p.id
        HAVING p.numTags = COUNT(t)
SQL;
        $query = $this->em->createQuery($dql);
        $query->setHydrationMode(Query::HYDRATE_ARRAY);

        $parametersResolver = $this->createMock(ParametersResolver::class);
        $paginator = new Paginator($parametersResolver);

        $view = $paginator->paginate($query, 1, 10, ['wrap-queries' => true]);
        $this->assertCount(3, $view);
    }

    /**
     * @test
     */
    function shouldBeAbleToPaginateMixedKeyArray()
    {
        $this->populate();

        $dql = <<<SQL
        SELECT p, t, p.title FROM
          Test\Fixture\Entity\Shop\Product p
        LEFT JOIN
          p.tags t
SQL;
        $query = $this->em->createQuery($dql);

        $parametersResolver = $this->createMock(ParametersResolver::class);
        $paginator = new Paginator($parametersResolver);

        $view = $paginator->paginate($query, 1, 10);
        $this->assertCount(3, $view);
        $items = $view->getItems();
        // and should be hydrated as array
        $this->assertTrue(isset($items[0]['title']));
    }

    /**
     * @test
     */
    function shouldBeAbleToPaginateCaseBasedQuery()
    {
        if (version_compare(\Doctrine\ORM\Version::VERSION, '2.2.0-DEV', '<')) {
            $this->markTestSkipped('Only recent orm version can test against this query.');
        }
        $this->populate();

        $dql = <<<SQL
            SELECT p,
              CASE
                WHEN p.title LIKE :keyword
                  AND p.description LIKE :keyword
                THEN 0

                WHEN p.title LIKE :keyword
                THEN 1

                WHEN p.description LIKE :keyword
                THEN 2

                ELSE 3
              END AS relevance
            FROM Test\Fixture\Entity\Shop\Product p
            WHERE (
              p.title LIKE :keyword
              OR p.description LIKE :keyword
            )
            GROUP BY p.id
            ORDER BY relevance ASC, p.id DESC
SQL;
        $query = $this->em->createQuery($dql);
        $query->setParameter('keyword', '%Star%');
        $query->setHydrationMode(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $parametersResolver = $this->createMock(ParametersResolver::class);
        $paginator = new Paginator($parametersResolver);

        $view = $paginator->paginate($query, 1, 10);
        $this->assertCount(1, $view);
        $items = $view->getItems();
        // and should be hydrated as array
        $this->assertEquals('Starship', $items[0][0]['title']);
        $this->assertEquals(1, $items[0]['relevance']);
    }

    /**
     * @test
     */
    function shouldUseOutputWalkersIfHinted()
    {
        $this->populate();

        $dql = <<<SQL
        SELECT p, t
        FROM Test\Fixture\Entity\Shop\Product p
        INNER JOIN p.tags t
        GROUP BY p.id
        HAVING p.numTags = COUNT(t)
SQL;
        $query = $this->em->createQuery($dql);
        $query->setHydrationMode(Query::HYDRATE_ARRAY);

        $parametersResolver = $this->createMock(ParametersResolver::class);
        $paginator = new Paginator($parametersResolver);

        $view = $paginator->paginate($query, 1, 10, array('wrap-queries' => true));
        $this->assertCount(3, $view);
    }

    protected function getUsedEntityFixtures(): array
    {
        return [
            Product::class,
            Tag::class
        ];
    }

    private function populate()
    {
        $em = $this->getMockSqliteEntityManager();
        $cheep = new Tag;
        $cheep->setName('Cheep');

        $new = new Tag;
        $new->setName('New');

        $special = new Tag;
        $special->setName('Special');

        $starship = new Product;
        $starship->setTitle('Starship');
        $starship->setPrice(277.66);
        $starship->addTag($new);
        $starship->addTag($special);

        $cheese = new Product;
        $cheese->setTitle('Cheese');
        $cheese->setPrice(7.66);
        $cheese->addTag($cheep);

        $shoe = new Product;
        $shoe->setTitle('Shoe');
        $shoe->setPrice(2.66);
        $shoe->addTag($special);

        $em->persist($special);
        $em->persist($cheep);
        $em->persist($new);
        $em->persist($starship);
        $em->persist($cheese);
        $em->persist($shoe);
        $em->flush();
    }
}
