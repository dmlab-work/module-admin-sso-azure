<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Plugin;

use DmLab\AdminSsoAzure\Model\EntraPreset;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use Magento\Framework\Validator\EmailAddress;

/**
 * Fills a missing Entra `email` claim from `preferred_username` / `upn` so users
 * without a `mail` attribute can be JIT-created.
 *
 * The fallback drops `xms_edov`, so the identity is unverified: admin-sso may
 * create an admin with it but never adopts an existing one by email.
 */
class EntraEmailFallback
{
    /** Claims tried in order when `email` is absent. */
    private const FALLBACK_CLAIMS = ['preferred_username', 'upn'];

    /**
     * @param EmailAddress $emailValidator Validator Magento applies when saving an admin user.
     */
    public function __construct(
        private readonly EmailAddress $emailValidator
    ) {
    }

    /**
     * Rewrite Entra claims before the identity is built.
     *
     * @param IdentityFactory $subject
     * @param array<string,mixed> $claims
     * @param ProviderPresetInterface $preset
     * @return array{0: array<string,mixed>, 1: ProviderPresetInterface}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCreate(IdentityFactory $subject, array $claims, ProviderPresetInterface $preset): array
    {
        if (!$preset instanceof EntraPreset || $this->toString($claims['email'] ?? null) !== '') {
            return [$claims, $preset];
        }

        unset($claims['email'], $claims['xms_edov']);
        foreach (self::FALLBACK_CLAIMS as $name) {
            $candidate = $this->toString($claims[$name] ?? null);
            if ($candidate !== '' && $this->emailValidator->isValid($candidate)) {
                $claims['email'] = $candidate;
                break;
            }
        }

        return [$claims, $preset];
    }

    /**
     * Trimmed string value of a claim, or '' when it is missing, non-scalar or boolean.
     *
     * @param mixed $value
     */
    private function toString(mixed $value): string
    {
        return is_scalar($value) && !is_bool($value) ? trim((string)$value) : '';
    }
}
