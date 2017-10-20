<?php

namespace AppBundle\Redirection;

use AppBundle\Repository\ArticleRepository;
use AppBundle\Repository\EventRepository;
use AppBundle\Repository\RedirectionRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Handle dynamic redirections editable in the administration panel.
 */
class DynamicRedirectionsSubscriber implements EventSubscriberInterface
{
    const REDIRECTIONS = [
        '/evenements/' => '/evenements',
        '/comites/' => '/comites',
        '/articles/tout' => '/articles',
        '/article/' => '/articles/',
        '/amp/article/' => '/amp/articles/',
    ];

    private $redirectRepository;
    private $router;
    private $eventRepository;
    private $articleRepository;

    public function __construct(
        RedirectionRepository $redirectionRepository,
        EventRepository $eventRepository,
        ArticleRepository $articleRepository,
        RouterInterface $router
    ) {
        $this->redirectRepository = $redirectionRepository;
        $this->router = $router;
        $this->eventRepository = $eventRepository;
        $this->articleRepository = $articleRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->getException() instanceof NotFoundHttpException || !$event->getException()) {
            return;
        }

        $requestUri = rtrim($event->getRequest()->getRequestUri(), '/');

        if ($redirection = $this->redirectRepository->findOneByOriginUri($requestUri)) {
            $event->setResponse(new RedirectResponse($redirection->getTo(), $redirection->getType()));

            return;
        }

        $redirectCode = Response::HTTP_MOVED_PERMANENTLY;
        foreach (self::REDIRECTIONS as $patternToMatch => $urlToRedirect) {
            if (0 !== strpos($requestUri, $patternToMatch)) {
                continue;
            }

            if ('/evenements/' === $patternToMatch
                && ($routeParams = $this->router->match($requestUri))
                && isset($routeParams['uuid'])
                && ($eventEntity = $this->eventRepository->findOneByUuid($routeParams['uuid']))
                && !$eventEntity->isPublished()) {
                $redirectCode = Response::HTTP_FOUND;
            }

            if (0 === strpos($requestUri, '/article/')) {
                $pathToContent = substr($requestUri, 9);
                $urlToRedirect = null !== ($article = $this->articleRepository->findOnePublishedBySlug($pathToContent))
                    ? $urlToRedirect = $this->router->generate('article_view', [
                        'categorySlug' => $article->getCategory()->getSlug(),
                        'articleSlug' => $article->getSlug(),
                    ])
                    : substr_replace($requestUri, '/articles/', 0, 10);
            }

            if (0 === strpos($requestUri, '/amp/article/')) {
                $pathToContent = substr($requestUri, 13);
                $urlToRedirect = null !== ($article = $this->articleRepository->findOnePublishedBySlug($pathToContent))
                    ? $urlToRedirect = $this->router->generate('amp_article_view', [
                        'categorySlug' => $article->getCategory()->getSlug(),
                        'articleSlug' => $article->getSlug(),
                    ])
                    : substr_replace($requestUri, '/amp/articles/', 0, 14);
            }

            $event->setResponse(new RedirectResponse($urlToRedirect, $redirectCode));

            return;
        }
    }
}
