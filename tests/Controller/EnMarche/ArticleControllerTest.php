<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\Controller\EnMarche\ArticleController;
use AppBundle\DataFixtures\ORM\LoadArticleData;
use AppBundle\DataFixtures\ORM\LoadLiveLinkData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\SqliteWebTestCase;

/**
 * @group functional
 * @group article
 */
class ArticleControllerTest extends SqliteWebTestCase
{
    use ControllerTestTrait;

    public function testRedirectArticlePublished()
    {
        $this->client->request(Request::METHOD_GET, '/article/outre-mer');

        $this->assertResponseStatusCode(Response::HTTP_MOVED_PERMANENTLY, $this->client->getResponse());

        $this->assertClientIsRedirectedTo('/articles/actualites/outre-mer', $this->client);
        $this->client->followRedirect();

        $this->assertResponseStatusCode(Response::HTTP_OK, $response = $this->client->getResponse());
    }

    public function testArticlePublished()
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/articles/actualites/outre-mer');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response = $this->client->getResponse());
        $this->assertSame(1, $crawler->filter('html:contains("An exhibit of Markdown")')->count());
        $this->assertContains('<img src="/assets/images/article.jpg', $this->client->getResponse()->getContent());
    }

    public function testArticleWithoutImage()
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/articles/discours/sans-image');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response = $this->client->getResponse());
        $this->assertSame(1, $crawler->filter('html:contains("An exhibit of Markdown")')->count());
        $this->assertNotContains('<img src="/assets/images/article.jpg', $this->client->getResponse()->getContent());
    }

    public function testArticleDraft()
    {
        $this->client->request(Request::METHOD_GET, '/articles/actualites/brouillon');
        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    /**
     * For this test, the pagination size is forced to ease understanding.
     *
     * @dataProvider dataProviderIsPaginationValid
     *
     * @param bool $expected
     * @param int  $articlesCount
     * @param int  $requestedPageNumber
     */
    public function testIsPaginationValid(bool $expected, int $articlesCount, int $requestedPageNumber)
    {
        $reflectionMethod = new \ReflectionMethod(ArticleController::class, 'isPaginationValid');
        $reflectionMethod->setAccessible(true);

        $articleController = $this->getMockBuilder(ArticleController::class)
            ->setMethods(['isPaginationValid'])
            ->getMock();

        $actual = $reflectionMethod->invoke($articleController, $articlesCount, $requestedPageNumber, 5);
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderIsPaginationValid(): array
    {
        return [
            [false,  0,  1],
            [true,   1,  1],
            [true,   5,  1],
            [false,  5,  2],
            [true,   6,  1],
            [true,   6,  2],
        ];
    }

    public function testRssFeed()
    {
        $this->client->request(Request::METHOD_GET, '/feed.xml');
        $this->assertResponseStatusCode(Response::HTTP_OK, $response = $this->client->getResponse());
        $this->assertSame('application/rss+xml', $response->headers->get('Content-Type'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadArticleData::class,
            LoadLiveLinkData::class,
        ]);
    }

    protected function tearDown()
    {
        $this->kill();

        parent::tearDown();
    }
}
