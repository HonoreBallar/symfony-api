<?php

namespace App\Serializer;

use App\Entity\Category;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Turns Category::coverUrl into an absolute URL (http://host/uploads/categories/...).
 */
final class CategoryNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'category_normalizer_already_called';

    public function __construct(private readonly UrlHelper $urlHelper)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->normalizer->normalize($data, $format, $context + [self::ALREADY_CALLED => true]);

        if (\is_array($normalized) && isset($normalized['coverUrl'])) {
            $normalized['coverUrl'] = $this->urlHelper->getAbsoluteUrl($normalized['coverUrl']);
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Category && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Category::class => false];
    }
}
