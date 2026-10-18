<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit\Model;

use DmLab\AdminSsoAzure\Model\Config;
use DmLab\AdminSsoAzure\Model\EntraPreset;
use DmLab\SsoCore\Api\DiscoveryIssuerPolicyInterface;
use DmLab\SsoCore\Api\EmailVerificationResolverInterface;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntraPresetTest extends TestCase
{
    /** @var EntraPreset preset under test, built over an empty Entra config. */
    private EntraPreset $preset;

    protected function setUp(): void
    {
        $this->preset = $this->preset();
    }

    /**
     * Build a preset over an Entra config stubbed from the given store values.
     *
     * @param array<string,mixed> $storeValues
     */
    private function preset(array $storeValues = []): EntraPreset
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $storeValues[$path] ?? null);

        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')
            ->willReturnCallback(
                static fn (string $fileId) => 'https://magento.loc/static/' . $fileId
            );

        return new EntraPreset(new Config($scopeConfig), $assetRepository);
    }

    public function testImplementsProviderPresetContract(): void
    {
        self::assertInstanceOf(ProviderPresetInterface::class, $this->preset);
    }

    public function testExposesIdentity(): void
    {
        self::assertSame('azure', $this->preset->getCode());
        self::assertSame('Microsoft Entra ID', $this->preset->getLabel());
    }

    public function testDefaultScopesAreOpenidProfileEmail(): void
    {
        self::assertSame(
            ['openid', 'profile', 'email'],
            $this->preset->getDefaultScopes()
        );
    }

    public function testGroupsClaimIsGroups(): void
    {
        self::assertSame('groups', $this->preset->getGroupsClaim());
    }

    public function testBrandingMetadata(): void
    {
        self::assertSame('Sign in with Microsoft', $this->preset->getButtonLabel());
        self::assertSame(
            'https://magento.loc/static/DmLab_AdminSsoAzure::images/microsoft.svg',
            $this->preset->getButtonIconUrl()
        );
    }

    /** The branded asset id must resolve to a file that actually ships. */
    public function testBrandingAssetFileExists(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 3) . '/view/adminhtml/web/images/microsoft.svg'
        );
    }

    public function testDiscoveryUrlFromTenantGuid(): void
    {
        $url = $this->preset->buildDiscoveryUrl([
            'tenant' => '11111111-2222-3333-4444-555555555555',
        ]);

        self::assertSame(
            'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555'
            . '/v2.0/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlFromVerifiedDomainTenant(): void
    {
        $url = $this->preset->buildDiscoveryUrl(['tenant' => 'contoso.onmicrosoft.com']);

        self::assertSame(
            'https://login.microsoftonline.com/contoso.onmicrosoft.com'
            . '/v2.0/.well-known/openid-configuration',
            $url
        );
    }

    /**
     * The multi-tenant meta values advertise a templated `{tenantid}` issuer that
     * never matches a real token's concrete-GUID `iss`, so admin SSO rejects them
     * rather than let a login fail after a successful authorization redirect. The
     * check is case-insensitive and applies after trimming.
     */
    public function testDiscoveryUrlRejectsMultiTenantMetaValues(): void
    {
        foreach (['common', 'organizations', 'consumers', 'COMMON', '  /organizations/ '] as $tenant) {
            try {
                $this->preset->buildDiscoveryUrl(['tenant' => $tenant]);
                self::fail("Expected rejection of multi-tenant value: '$tenant'");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('single-tenant', $e->getMessage());
            }
        }
    }

    public function testDiscoveryUrlStripsSurroundingWhitespaceAndSlashes(): void
    {
        $url = $this->preset->buildDiscoveryUrl(['tenant' => '  /contoso.onmicrosoft.com/ ']);

        self::assertSame(
            'https://login.microsoftonline.com/contoso.onmicrosoft.com'
            . '/v2.0/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlFallsBackToStoredTenant(): void
    {
        $preset = $this->preset([Config::XML_PATH_TENANT => '11111111-2222-3333-4444-555555555555']);

        self::assertSame(
            'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555'
            . '/v2.0/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl([])
        );
    }

    public function testExplicitTenantOverridesStoredValue(): void
    {
        $preset = $this->preset([Config::XML_PATH_TENANT => 'stored-tenant']);

        self::assertSame(
            'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555'
            . '/v2.0/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl(['tenant' => '11111111-2222-3333-4444-555555555555'])
        );
    }

    /**
     * An explicit blank tenant key wins over the stored value (intentional
     * "clear" semantics via array_key_exists), so it throws rather than falling
     * back to the stored tenant.
     */
    public function testExplicitBlankTenantOverridesStoredValue(): void
    {
        $preset = $this->preset([Config::XML_PATH_TENANT => 'stored-tenant']);

        $this->expectException(\InvalidArgumentException::class);
        $preset->buildDiscoveryUrl(['tenant' => '']);
    }

    public function testDiscoveryUrlThrowsWhenTenantMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->preset->buildDiscoveryUrl([]);
    }

    public function testDiscoveryUrlThrowsWhenTenantBlank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->preset->buildDiscoveryUrl(['tenant' => '   ']);
    }

    public function testImplementsEmailVerificationResolver(): void
    {
        self::assertInstanceOf(EmailVerificationResolverInterface::class, $this->preset);
    }

    /**
     * @return array<string,array{array<string,mixed>,bool}>
     */
    public static function emailVerifiedCases(): array
    {
        return [
            'xms_edov true' => [['xms_edov' => true], true],
            'xms_edov 1' => [['xms_edov' => 1], true],
            'xms_edov "1"' => [['xms_edov' => '1'], true],
            'xms_edov "TRUE"' => [['xms_edov' => 'TRUE'], true],
            'xms_edov missing' => [[], false],
            'xms_edov false' => [['xms_edov' => false], false],
            'xms_edov 0' => [['xms_edov' => 0], false],
            'xms_edov "0"' => [['xms_edov' => '0'], false],
            'xms_edov array' => [['xms_edov' => [true]], false],
            'email_verified ignored' => [['email' => 'a@contoso.com', 'email_verified' => true], false],
        ];
    }

    /**
     * @param array<string,mixed> $claims
     */
    #[DataProvider('emailVerifiedCases')]
    public function testResolveEmailVerifiedFromXmsEdovOnly(array $claims, bool $expected): void
    {
        $result = $this->preset->resolveEmailVerified($claims);

        self::assertNotNull($result);
        self::assertSame($expected, $result);
    }

    public function testImplementsDiscoveryIssuerPolicy(): void
    {
        self::assertInstanceOf(DiscoveryIssuerPolicyInterface::class, $this->preset);
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function issuerMatchCases(): array
    {
        return [
            'GUID lower case' => ['11111111-2222-3333-4444-55555555aaaa', true],
            'GUID upper case' => ['11111111-2222-3333-4444-55555555AAAA', true],
            'GUID with slashes' => [' /11111111-2222-3333-4444-555555555555/ ', true],
            'custom domain' => ['contoso.com', false],
            'onmicrosoft domain' => ['contoso.onmicrosoft.com', false],
            'blank' => ['', true],
            'whitespace only' => ['  ', true],
        ];
    }

    #[DataProvider('issuerMatchCases')]
    public function testRequiresIssuerMatchOnlyForNonDomainTenant(string $tenant, bool $expected): void
    {
        self::assertSame($expected, $this->preset->requiresIssuerMatch(['tenant' => $tenant]));
    }

    public function testRequiresIssuerMatchFallsBackToStoredTenant(): void
    {
        self::assertFalse(
            $this->preset([Config::XML_PATH_TENANT => 'contoso.com'])->requiresIssuerMatch([])
        );
        self::assertTrue(
            $this->preset([Config::XML_PATH_TENANT => '11111111-2222-3333-4444-555555555555'])
                ->requiresIssuerMatch([])
        );
    }

    public function testRequiresIssuerMatchWithNoTenantAnywhereIsStrict(): void
    {
        self::assertTrue($this->preset->requiresIssuerMatch([]));
    }

    public function testRequiresIssuerMatchExplicitTenantOverridesStoredValue(): void
    {
        $preset = $this->preset([Config::XML_PATH_TENANT => 'contoso.com']);

        self::assertTrue($preset->requiresIssuerMatch(['tenant' => '11111111-2222-3333-4444-555555555555']));
        self::assertTrue($preset->requiresIssuerMatch(['tenant' => '']));
    }
}
