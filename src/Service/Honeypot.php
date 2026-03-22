<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Service;

use Windwalker\Core\Router\Navigator;
use Windwalker\Core\Router\RouteUri;
use Windwalker\DI\Attributes\Service;
use Windwalker\DOM\HTMLElement;
use Windwalker\Utilities\Arr;

#[Service]
class Honeypot
{
    public function __construct(protected Navigator $nav)
    {
    }

    public function link(mixed $route = null, string $paramName = '_ref'): HTMLElement
    {
        $el = HTMLElement::new('a');
        $el->setAttribute('href', (string) ($route ?? $this->route($paramName)));
        $el->setAttribute('rel', 'nofollow');
        $el->style->height = '0';
        $el->style->position = 'absolute';
        $el->style->top = '-2000px';
        $el->style->left = '-2000px';

        return $el;
    }

    public function route(string $route = 'home', string $paramName = '_ref'): RouteUri
    {
        return $this->nav->to($route)->var($paramName, static::getBlockWord());
    }

    public static function getBlockWord(): string
    {
        $words = static::getBlockWords();

        shuffle($words);

        return $words[0] ?? '';
    }

    public static function getBlockWords(): array
    {
        $words = static::getWords();

        $num = (int) env('HONEYPOT_BLOCK_WORDS', '3');

        return array_slice($words, 0, $num);
    }

    public static function getWordsShuffle(): array
    {
        $words = static::getWords();

        shuffle($words);

        return $words;
    }

    public static function getWords(): array
    {
        $words = env('HONEYPOT_WORDS');

        if ($words) {
            $words = Arr::explodeAndClear(',', $words);
        }

        if ($words) {
            return $words;
        }

        return [
            'internal',
            'preview',
            'affiliate',
            'google',
            'newsletter',
            'partner',
            'campaign',
            'referral',
            'organic',
            'social',
        ];
    }
}
