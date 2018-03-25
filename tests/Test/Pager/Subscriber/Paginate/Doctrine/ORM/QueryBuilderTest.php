<?php

namespace Test\Pager\Subscriber\Paginate\Doctrine\ORM;

use Knp\Component\Pager\ParametersResolver;
use Test\Tool\BaseTestCaseORM;
use Knp\Component\Pager\Paginator;
use Test\Fixture\Entity\Article;

class QueryBuilderTest extends BaseTestCaseORM
{
    /**
     * @test
     */
    function shouldPaginateSimpleDoctrineQuery()
    {
        $this->populate();
        $parametersResolver = $this->createMock(ParametersResolver::class);
        $paginator = new Paginator($parametersResolver);

        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('a')
            ->from(Article::class, 'a')
        ;
        $view = $paginator->paginate($qb, 1, 2);

        $this->assertEquals(1, $view->getCurrentPageNumber());
        $this->assertEquals(2, $view->getItemNumberPerPage());
        $this->assertEquals(4, $view->getTotalItemCount());

        $items = $view->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals('summer', $items[0]->getTitle());
        $this->assertEquals('winter', $items[1]->getTitle());
    }

    protected function getUsedEntityFixtures(): array
    {
        return [Article::class];
    }

    private function populate()
    {
        $em = $this->getMockSqliteEntityManager();
        $summer = new Article;
        $summer->setTitle('summer');

        $winter = new Article;
        $winter->setTitle('winter');

        $autumn = new Article;
        $autumn->setTitle('autumn');

        $spring = new Article;
        $spring->setTitle('spring');

        $em->persist($summer);
        $em->persist($winter);
        $em->persist($autumn);
        $em->persist($spring);
        $em->flush();
    }
}
