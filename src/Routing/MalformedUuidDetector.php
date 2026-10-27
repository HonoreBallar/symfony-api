<?php

namespace App\Routing;

use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Tells apart "no such route" from "the route exists but an {id} segment is not a valid UUID"
 * (routes declare Requirement::UUID, so a malformed id makes the whole route miss).
 */
final readonly class MalformedUuidDetector
{
    /** Any valid UUID works: it only has to satisfy Requirement::UUID. */
    private const string PLACEHOLDER = '00000000-0000-7000-8000-000000000000';

    public function __construct(
        private UrlMatcherInterface $matcher,
    ) {
    }

    /**
     * @return string|null the name of the malformed route parameter (e.g. "id"), or null when the path matches no route anyway
     */
    public function detect(string $pathInfo): ?string
    {
        $segments = explode('/', $pathInfo);

        foreach ($segments as $index => $segment) {
            if ('' === $segment || preg_match('{^'.Requirement::UUID.'$}', $segment)) {
                continue;
            }

            $candidate = $segments;
            $candidate[$index] = self::PLACEHOLDER;

            try {
                $parameters = $this->matcher->match(implode('/', $candidate));
            } catch (MethodNotAllowedException) {
                // The path exists for another HTTP method: the segment is still the culprit.
                return 'id';
            } catch (ExceptionInterface) {
                continue;
            }

            return array_search(self::PLACEHOLDER, $parameters, true) ?: 'id';
        }

        return null;
    }
}
