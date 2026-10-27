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

/**
 * Flags Entra's implicit-flow groups overage (`hasgroups: true`, no `groups`) as a
 * distributed `groups` claim, so the identity reports incomplete groups and admin-sso
 * refuses the login as it does for the `_claim_names` overage.
 */
class EntraGroupsOverage
{
    /**
     * Mark a `hasgroups` overage as a distributed `groups` claim before the identity is built.
     *
     * @param IdentityFactory $subject
     * @param array<string,mixed> $claims
     * @param ProviderPresetInterface $preset
     * @return array{0: array<string,mixed>, 1: ProviderPresetInterface}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCreate(IdentityFactory $subject, array $claims, ProviderPresetInterface $preset): array
    {
        $hasGroups = $claims['hasgroups'] ?? null;
        if (!$preset instanceof EntraPreset
            || ($hasGroups !== true && $hasGroups !== 'true')
            || isset($claims['groups'])
        ) {
            return [$claims, $preset];
        }

        $names = is_array($claims['_claim_names'] ?? null) ? $claims['_claim_names'] : [];
        $names['groups'] ??= 'hasgroups';
        $claims['_claim_names'] = $names;

        return [$claims, $preset];
    }
}
