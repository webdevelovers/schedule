<?php

declare(strict_types=1);

namespace WebDevelovers\Schedule\Tests;

use Symfony\Component\Yaml\Yaml;
use WebDevelovers\Schedule\Humanizer\HumanizerTranslatorInterface;
use function str_replace;

readonly class ArrayTranslator implements HumanizerTranslatorInterface
{
    /** @param array<string,string> $messages */
    public function __construct(private array $messages)
    {
    }

    public static function fromYaml(string $path): self
    {
        $parsed = Yaml::parseFile($path);
        return new self(is_array($parsed) ? $parsed : []);
    }

    /** @param array<string,string|int> $parameters */
    public function trans(string $key, array $parameters = [], string|null $domain = null, string|null $locale = null): string
    {
        $message = $this->messages[$key] ?? $key;

        foreach ($parameters as $k => $v) {
            $placeholder = (str_starts_with($k, '%') && str_ends_with($k, '%')) ? $k : '%' . $k . '%';
            $message = str_replace($placeholder, (string) $v, $message);
        }

        return $message;
    }
}
