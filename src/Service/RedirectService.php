<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Service;

use Lyrasoft\Firewall\Entity\Redirect;
use Lyrasoft\Firewall\Repository\RedirectRepository;
use Lyrasoft\Luna\Services\LocaleService;
use Psr\Http\Message\ResponseInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Application\Context\AppRequestInterface;
use Windwalker\Data\Collection;
use Windwalker\DI\Attributes\Autowire;
use Windwalker\DI\Attributes\Service;
use Windwalker\Http\Response\RedirectResponse;
use Windwalker\ORM\ORM;
use Windwalker\Uri\UriHelper;
use Windwalker\Utilities\Str;

use function Windwalker\chronos;
use function Windwalker\raw;
use function Windwalker\response;

#[Service]
class RedirectService
{
    public function __construct(
        #[Autowire]
        protected RedirectRepository $repository,
        protected AppContext $app,
        protected LocaleService $localeService,
    ) {
        //
    }

    public function matchFromList(string $route, iterable $redirects, ?array &$matches = null): ?Redirect
    {
        $route = urldecode($route);
        $route = rtrim($route, '/');

        /** @var Redirect $redirect */
        foreach ($redirects as $redirect) {
            $matched = $this->matchSingleRedirect($route, $redirect, $matches);

            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    public function matchSingleRedirect(string $route, Redirect $redirect, ?array &$matches = null): ?Redirect
    {
        // Do not modify origin object
        $redirect = clone $redirect;

        $src = rtrim($redirect->getSrc(), '/');

        // Handle lang
        $handleLang = $redirect->isHandleLang() && $this->localeService->isEnabled();
        $langAlias = null;

        if ($handleLang) {
            $route = $this->stripLangAlias($route, $langAlias);
        }

        $destRedirect = null;

        if ($redirect->isRegexEnabled()) {
            $src = $this->parseWildcards($src);

            $regex = '/';

            // Is absolute
            if (str_starts_with($src, '/')) {
                $regex .= '^';
                $route = Str::ensureLeft($route, '/');
            }

            $regex .= str_replace('/', '\\/', $src) . '/';

            if (preg_match($regex, $route, $matches)) {
                $destRedirect = $redirect;
            }
        } elseif (Str::ensureLeft($route, '/') === Str::ensureLeft($src, '/')) {
            $destRedirect = $redirect;
        }

        if (!$destRedirect) {
            return null;
        }

        if ($handleLang) {
            $dest = $destRedirect->getDest();

            if (str_contains($dest, '{lang}')) {
                $dest = str_replace('{lang}', (string) $langAlias, $dest);
            } elseif ($langAlias && !UriHelper::isAbsolute($dest)) {
                $dest = $langAlias . '/' . $dest;
            }

            $destRedirect->setDest($dest);
        }

        return $destRedirect;
    }

    /**
     * @param  string    $route
     * @param  iterable  $redirects
     *
     * @return  array{ 0: string, 1: Redirect }|null
     */
    public function matchListAndProcessRegex(
        string $route,
        iterable $redirects,
    ): ?array {
        $redirect = $this->matchFromList($route, $redirects, $matches);

        if (!$redirect) {
            return null;
        }

        $dest = $redirect->getDest();

        if ($redirect->isRegexEnabled()) {
            $dest = $this->replaceVariables($dest, (array) $matches);
        }

        return [$dest, $redirect];
    }

    /**
     * @param  string              $route
     * @param  iterable<Redirect>  $redirects
     * @param  bool                $instant
     *
     * @return  ResponseInterface|null
     */
    public function matchAndRedirect(
        string $route,
        iterable $redirects,
        bool $instant = false
    ): ?ResponseInterface {
        $result = $this->matchListAndProcessRegex($route, $redirects);

        if (!$result) {
            return null;
        }

        [$dest, $redirect] = $result;

        $this->updateHits($redirect);

        return $this->app->redirect($dest, $redirect->getStatus(), $instant);
    }

    public function updateHits(Redirect $redirect): void
    {
        if (!$redirect->getId()) {
            return;
        }

        $orm = $this->repository->getORM();

        $orm->update(Redirect::class)
            ->set('hits', raw('hits + 1'))
            ->set('last_hit', chronos())
            ->where('id', $redirect->getId())
            ->execute();
    }

    /**
     * @param  string|\BackedEnum|array|null  $type
     *
     * @return  Collection<Redirect>
     */
    public function getAvailableRedirects(string|\BackedEnum|array|null $type): Collection
    {
        return $this->repository->getAvailableSelector($type)
            ->all(Redirect::class);
    }

    protected function parseWildcards(string $src): string
    {
        $src = str_replace(
            ['\\**', '\\*'],
            ['__DWC__', '__WC__'],
            $src
        );

        $src = (string) str_replace(
            '**',
            '(.+)',
            $src
        );

        $src = (string) str_replace(
            '*',
            '([^/]+)',
            $src
        );

        $src = str_replace(
            ['__DWC__', '__WC__'],
            ['\\**', '\\*'],
            $src
        );

        return $src;
    }

    public function replaceVariables(string $dest, array $matches): string
    {
        foreach ($matches as $i => $value) {
            $key = '$' . $i;

            $dest = str_replace($key, $value, $dest);
        }

        return $dest;
    }

    protected function stripLangAlias(string $route, ?string &$alias = null): string
    {
        $langs = $this->localeService->getAvailableLanguages();

        foreach ($langs as $lang) {
            if (str_starts_with($route, $lang->getAlias())) {
                $route = Str::removeLeft($route, $lang->getAlias());
                $route = Str::removeLeft($route, '/') ?: '/';

                $alias = $lang->getAlias();

                return $route;
            }
        }

        return $route;
    }
}
