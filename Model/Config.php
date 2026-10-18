<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Typed reader over the Entra-specific admin configuration.
 *
 * Entra is standard OIDC, so the only IdP-specific setting is the tenant (a
 * directory GUID or verified domain — single-tenant only); it lives under the
 * admin-sso section (group `azure`). {@see EntraPreset} reads it here to build the
 * discovery URL, keeping the provider-agnostic core free of Entra config paths.
 */
class Config
{
    /** Entra tenant: directory GUID or verified domain (single-tenant only). */
    public const XML_PATH_TENANT = 'dmlab_admin_sso/azure/tenant';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Configured Entra tenant, or null when unset.
     */
    public function getTenant(): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_TENANT);
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
