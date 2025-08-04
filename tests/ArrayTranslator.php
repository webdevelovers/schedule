<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Tests;

use WebDevelovers\Schedule\Humanizer\HumanizerTranslatorInterface;
use function str_replace;

readonly class ArrayTranslator implements HumanizerTranslatorInterface
{
    /** @param array<string,string> $translations */
    public function __construct(private array $translations)
    {
    }

    public function trans(string $key, array $parameters = [], string|null $domain = null, string|null $locale = null): string
    {
        $string = $this->translations[$key] ?? $key;

        foreach ($parameters as $k => $v) {
            $string = str_replace($k, (string)$v, $string);
        }

        return $string;
    }
}
