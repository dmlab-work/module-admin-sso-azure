<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Model;

use DmLab\SsoCore\Api\ProviderPresetInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Microsoft Entra ID (Azure AD) OIDC preset for the admin-sso capability.
 *
 * Entra is standard OIDC, so this preset only describes it: how to build the
 * discovery URL from the configured tenant, which scopes and groups claim to
 * use, and the login button branding. All protocol work lives in `sso-core`,
 * all admin logic in `admin-sso`; a plugin registers this preset into
 * `admin-sso`'s PresetRegistry.
 *
 * The tenant comes from the admin config ({@see Config}); an explicit value in
 * the config array passed to {@see buildDiscoveryUrl} overrides the stored one,
 * keeping the URL a pure function of its inputs where the caller supplies them.
 */
class EntraPreset implements ProviderPresetInterface
{
    /** Stable machine code identifying Entra across the suite. */
    private const CODE = 'azure';

    /** Config key carrying the Entra tenant (directory GUID or verified domain). */
    public const CONFIG_TENANT = 'tenant';

    /**
     * Multi-tenant meta-identifiers rejected as tenant values. Their discovery
     * metadata advertises a templated issuer (`.../{tenantid}/v2.0`), so the
     * literal never equals a real token's concrete-GUID `iss` and every login
     * would fail issuer validation. Admin SSO is single-tenant only: use the
     * directory GUID or a verified domain, which resolve to a concrete issuer.
     */
    private const REJECTED_TENANTS = ['common', 'organizations', 'consumers'];

    /** Entra's tenant-scoped OIDC discovery endpoint (v2.0). */
    private const DISCOVERY_TEMPLATE =
        'https://login.microsoftonline.com/%s/v2.0/.well-known/openid-configuration';

    /** Login-button label shown on the admin login page. */
    private const BUTTON_LABEL = 'Sign in with Microsoft';

    /** Module-relative asset id of the login-button logo. */
    private const ICON_ASSET = 'DmLab_AdminSsoAzure::images/microsoft.svg';

    /**
     * @param Config $config
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly AssetRepository $assetRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'Microsoft Entra ID';
    }

    /**
     * Build Entra's OIDC discovery URL from the configured tenant.
     *
     * The tenant is used as the directory path segment and must identify a single
     * tenant — a directory GUID or a verified domain — so its discovery issuer is
     * concrete and matches the token `iss`. The multi-tenant meta values
     * (`common`/`organizations`/`consumers`) are rejected: their issuer is a
     * `{tenantid}` template that never matches a real token. Falls back to the
     * admin config when the passed array omits the tenant, so the core can call
     * this with only its generic fields and still get an Entra-configured URL.
     *
     * @param array<string,mixed> $config
     * @throws \InvalidArgumentException when the tenant is missing or multi-tenant.
     */
    public function buildDiscoveryUrl(array $config): string
    {
        $tenant = trim(
            $this->resolve($config, self::CONFIG_TENANT, $this->config->getTenant()),
            " \t/"
        );
        if ($tenant === '') {
            throw new \InvalidArgumentException('The Entra tenant is not configured.');
        }
        if (in_array(strtolower($tenant), self::REJECTED_TENANTS, true)) {
            throw new \InvalidArgumentException(
                'Admin SSO requires a single-tenant Entra directory. Use the directory (tenant) '
                . 'GUID or a verified domain, not "common"/"organizations"/"consumers".'
            );
        }

        return sprintf(self::DISCOVERY_TEMPLATE, rawurlencode($tenant));
    }

    /**
     * @inheritDoc
     *
     * Entra emits group memberships through the token configuration, not an OIDC
     * scope, so the groups scope is intentionally absent here.
     */
    public function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    /**
     * @inheritDoc
     */
    public function getGroupsClaim(): ?string
    {
        return 'groups';
    }

    /**
     * @inheritDoc
     */
    public function getButtonLabel(): string
    {
        return self::BUTTON_LABEL;
    }

    /**
     * @inheritDoc
     *
     * Resolves the shipped Microsoft logo through the asset repository so the URL
     * is theme/area-correct on the admin login page.
     */
    public function getButtonIconUrl(): ?string
    {
        return $this->assetRepository->getUrl(self::ICON_ASSET);
    }

    /**
     * Resolve a config value: an explicit key in the passed array wins (even if
     * blank, so a caller can intentionally clear it), otherwise fall back to the
     * stored admin config value.
     *
     * @param array<string,mixed> $config
     * @param string $key
     * @param string|null $fallback
     */
    private function resolve(array $config, string $key, ?string $fallback): string
    {
        if (array_key_exists($key, $config)) {
            return (string)$config[$key];
        }

        return (string)($fallback ?? '');
    }
}
