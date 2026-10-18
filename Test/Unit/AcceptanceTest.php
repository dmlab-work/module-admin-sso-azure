<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use DmLab\AdminSso\Model\ActiveProviderResolver;
use DmLab\AdminSso\Model\Config as AdminSsoConfig;
use DmLab\AdminSso\Model\Config\Source\ActiveProvider;
use DmLab\AdminSso\Model\Oidc\AuthorizationStarter;
use DmLab\AdminSso\Model\PresetRegistry;
use DmLab\AdminSsoAzure\Model\Config as AzureConfig;
use DmLab\AdminSsoAzure\Model\EntraPreset;
use DmLab\SsoCore\Api\AuthorizationStateStorageInterface;
use DmLab\SsoCore\Model\Oidc\AuthorizationRequestFactory;
use DmLab\SsoCore\Model\Oidc\DiscoveryClient;
use DmLab\SsoCore\Model\Oidc\ProviderMetadata;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance test for the module's contract with `admin-sso`: with the Entra
 * preset registered (as di.xml declares) into the capability core, `azure` shows
 * up in the provider dropdown and drives the OIDC authorization URL end-to-end.
 *
 * Discovery and token exchange are stubbed — this proves the seam (registry →
 * dropdown, registry → resolver → discovery URL → auth request), not the network.
 */
class AcceptanceTest extends TestCase
{
    /** Entra directory (tenant) GUID used across the acceptance scenario. */
    private const TENANT = '11111111-2222-3333-4444-555555555555';

    /** OIDC client id issued by the IdP. */
    private const CLIENT_ID = 'entra-example-client-id';

    /** Authorization endpoint the stubbed discovery document advertises. */
    private const AUTH_ENDPOINT =
        'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/oauth2/v2.0/authorize';

    /** Admin callback URL registered as the redirect URI. */
    private const CALLBACK_URL = 'https://magento.loc/admin/adminsso/sso/callback';

    public function testAzureAppearsInProviderDropdown(): void
    {
        $source = new ActiveProvider($this->registry());

        $options = $source->toOptionArray();
        $azure = $this->optionByValue($options, 'azure');

        self::assertNotNull($azure, 'The "azure" option is missing from the provider dropdown.');
        self::assertSame('Microsoft Entra ID', (string)$azure['label']);
    }

    public function testAzureDrivesTheAuthorizationUrl(): void
    {
        $registry = $this->registry();
        $capturedDiscoveryUrl = null;

        $discoveryClient = $this->createStub(DiscoveryClient::class);
        $discoveryClient->method('discover')->willReturnCallback(
            function (string $url) use (&$capturedDiscoveryUrl): ProviderMetadata {
                $capturedDiscoveryUrl = $url;

                return new ProviderMetadata(
                    'https://login.microsoftonline.com/' . self::TENANT . '/v2.0',
                    self::AUTH_ENDPOINT,
                    'https://login.microsoftonline.com/' . self::TENANT . '/oauth2/v2.0/token',
                    'https://login.microsoftonline.com/' . self::TENANT . '/discovery/v2.0/keys'
                );
            }
        );

        $starter = new AuthorizationStarter(
            $this->adminConfig(),
            new ActiveProviderResolver($this->activeProviderScopeConfig(), $registry),
            $discoveryClient,
            new AuthorizationRequestFactory($this->fixedRandom()),
            $this->createStub(AuthorizationStateStorageInterface::class),
            $this->backendUrl()
        );

        $url = $starter->start();

        // The Entra preset resolved by the registry built the discovery URL.
        self::assertSame(
            'https://login.microsoftonline.com/' . self::TENANT
            . '/v2.0/.well-known/openid-configuration',
            $capturedDiscoveryUrl
        );

        // The auth URL is anchored on the discovered endpoint and carries the
        // Entra default scopes (no `groups` scope — Entra emits groups via the
        // token configuration) and client id.
        self::assertStringStartsWith(self::AUTH_ENDPOINT . '?', $url);
        self::assertStringContainsString('client_id=' . self::CLIENT_ID, $url);
        self::assertStringContainsString('scope=openid%20profile%20email', $url);
        self::assertStringNotContainsString('groups', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString('redirect_uri=' . rawurlencode(self::CALLBACK_URL), $url);
    }

    /**
     * PresetRegistry seeded with the Entra preset exactly as etc/di.xml wires it.
     */
    private function registry(): PresetRegistry
    {
        return new PresetRegistry(['azure' => $this->entraPreset()]);
    }

    /**
     * Entra preset over a config whose tenant is set to {@see TENANT}.
     */
    private function entraPreset(): EntraPreset
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string =>
                $path === AzureConfig::XML_PATH_TENANT ? self::TENANT : null
        );

        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')->willReturn('https://magento.loc/static/microsoft.svg');

        return new EntraPreset(new AzureConfig($scopeConfig), $assetRepository);
    }

    /**
     * admin-sso config stub: SSO enabled with a configured client id.
     */
    private function adminConfig(): AdminSsoConfig
    {
        $config = $this->createStub(AdminSsoConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getClientId')->willReturn(self::CLIENT_ID);

        return $config;
    }

    /**
     * Scope-config stub selecting `azure` as the active provider.
     */
    private function activeProviderScopeConfig(): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string =>
                $path === ActiveProviderResolver::XML_PATH_ACTIVE_PROVIDER ? 'azure' : null
        );

        return $scopeConfig;
    }

    /**
     * Random stub returning fixed bytes so the auth request is deterministic.
     */
    private function fixedRandom(): Random
    {
        $random = $this->createStub(Random::class);
        $random->method('getRandomBytes')->willReturn(str_repeat("\x01", 32));

        return $random;
    }

    /**
     * Backend-URL stub returning the fixed admin callback URL.
     */
    private function backendUrl(): BackendUrlInterface
    {
        $backendUrl = $this->createStub(BackendUrlInterface::class);
        $backendUrl->method('getUrl')->willReturn(self::CALLBACK_URL);

        return $backendUrl;
    }

    /**
     * Find a dropdown option by its value.
     *
     * @param array<int,array{value:mixed,label:mixed}> $options
     * @param string $value
     * @return array{value:mixed,label:mixed}|null
     */
    private function optionByValue(array $options, string $value): ?array
    {
        foreach ($options as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option;
            }
        }

        return null;
    }
}
