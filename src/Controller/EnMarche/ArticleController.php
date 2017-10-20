<?php

namespace AppBundle\Controller\EnMarche;

use AppBundle\Entity\Article;
use AppBundle\Entity\ArticleCategory;
use Psr\Cache\CacheItemPoolInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ArticleController extends Controller
{
    const PER_PAGE = 12;

    /**
     * @Route(
     *     "/articles/{category}/{page}",
     *     requirements={"category"="\w+", "page"="\d+"},
     *     defaults={"category"="tout", "page"=1, "_enable_campaign_silence"=true},
     *     name="articles_list"
     * )
     * @Method("GET")
     */
    public function actualitesAction(string $category, int $page, Request $request): Response
    {
        if ('/articles/'.ArticleCategory::DEFAULT_CATEGORY === $request->getRequestUri()) {
            return $this->redirectToRoute('articles_list', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $noFilterByCategory = new ArticleCategory('Toute l\'actualité', ArticleCategory::DEFAULT_CATEGORY);

        $categoriesRepo = $this->getDoctrine()->getRepository(ArticleCategory::class);
        $articleCategory = !ArticleCategory::isDefault($category)
            ? $categoriesRepo->findOneBySlug($category)
            : $noFilterByCategory;

        if (!$articleCategory) {
            throw $this->createNotFoundException();
        }

        $categories = $categoriesRepo->findAll();
        array_unshift($categories, $noFilterByCategory);
        $articlesRepo = $this->getDoctrine()->getRepository(Article::class);
        $articlesCount = $articlesRepo->countAllByCategory($category);

        if (!$this->isPaginationValid($articlesCount, $page)) {
            throw $this->createNotFoundException();
        }

        return $this->render('article/actualites.html.twig', [
            'current' => $articleCategory,
            'categories' => $categories,
            'articles' => $articlesRepo->findByCategoryPaginated($category, $page, self::PER_PAGE),
            'currentPage' => $page,
            'totalPages' => ceil($articlesCount / self::PER_PAGE),
        ]);
    }

    /**
     * @Route("/articles/{categorySlug}/{articleSlug}", defaults={"_enable_campaign_silence"=true}, name="article_view")
     * @Method("GET")
     * @Entity("article", expr="repository.findOnePublishedBySlugAndCategorySlug(articleSlug, categorySlug)")
     */
    public function articleAction(Article $article): Response
    {
        $latestArticles = $this->getDoctrine()->getRepository(Article::class)->findThreeLatestOtherThan($article);

        return $this->render('article/article.html.twig', [
            'article' => $article,
            'latestArticles' => $latestArticles,
        ]);
    }

    /**
     * @Route("/feed.xml", defaults={"_enable_campaign_silence"=true}, name="articles_feed")
     * @Method("GET")
     */
    public function feedAction(): Response
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->get('cache.app');
        $cachedRenderedFeed = $cache->getItem('rss_feed');

        if (!$cachedRenderedFeed->isHit()) {
            $generator = $this->get('app.feed_generator.article');
            $feed = $generator->buildFeed($this->getDoctrine()->getRepository(Article::class)->findAllForFeed());

            $cachedRenderedFeed->set($feed->render());
            $cachedRenderedFeed->expiresAfter($this->getParameter('feed_ttl') * 60);

            $cache->save($cachedRenderedFeed);
        }

        return new Response($cachedRenderedFeed->get(), Response::HTTP_OK, ['Content-Type' => 'application/rss+xml']);
    }

    /**
     * @param int $articlesCount
     * @param int $requestedPageNumber
     * @param int $itemsPerPage
     *
     * @return bool
     */
    private function isPaginationValid(int $articlesCount, int $requestedPageNumber, int $itemsPerPage = self::PER_PAGE): bool
    {
        if (!$articlesCount) {
            return false;
        }

        return $requestedPageNumber <= (int) ceil($articlesCount / $itemsPerPage);
    }
}
