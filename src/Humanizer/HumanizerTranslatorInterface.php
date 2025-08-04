<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Humanizer;

interface HumanizerTranslatorInterface
{
    /** @param array<string,string|int> $parameters */
    public function trans(string $key, array $parameters = [], string|null $domain = null, string|null $locale = null): string;
}
