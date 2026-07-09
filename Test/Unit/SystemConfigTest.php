<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\AdminSsoAzure\Test\Unit;

use MageDevGroup\AdminSsoAzure\Model\Config;
use MageDevGroup\AdminSsoAzure\Model\EntraPreset;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;

/**
 * Asserts adminhtml/system.xml exposes the Entra tenant field under the admin-sso
 * section, at the exact path {@see Config} reads, and that a value stored there
 * flows through {@see EntraPreset} into the discovery URL.
 */
class SystemConfigTest extends TestCase
{
    /** @var \DOMXPath */
    private \DOMXPath $xpath;

    protected function setUp(): void
    {
        $systemXml = dirname(__DIR__, 2) . '/etc/adminhtml/system.xml';
        self::assertFileExists($systemXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($systemXml));
        $this->xpath = new \DOMXPath($dom);
    }

    public function testAzureGroupLivesUnderAdminSsoSection(): void
    {
        $groups = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_admin_sso']/group[@id='azure']"
        );

        self::assertNotNull($groups);
        self::assertSame(1, $groups->length);
    }

    public function testTenantFieldMapsToConfigPath(): void
    {
        $fields = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_admin_sso']"
            . "/group[@id='azure']/field[@id='tenant']"
        );

        self::assertNotNull($fields);
        self::assertSame(1, $fields->length, 'Missing azure field \'tenant\'.');

        /** @var \DOMElement $field */
        $field = $fields->item(0);
        self::assertSame(
            0,
            $this->xpath->query('config_path', $field)->length,
            'Field \'tenant\' has a config_path override; its path is no longer id-derived.'
        );

        $group = $field->parentNode;
        $section = $group->parentNode;
        $derivedPath = $section->getAttribute('id') . '/'
            . $group->getAttribute('id') . '/'
            . $field->getAttribute('id');

        self::assertSame(Config::XML_PATH_TENANT, $derivedPath);
    }

    /**
     * Cross-group depends must be fully-qualified section/group/field paths;
     * Magento only wildcard-pads single-segment relative paths, so a two-segment
     * path resolves to a non-existent field and the show/hide toggle never binds.
     */
    public function testAzureGroupDependsAreFullyQualified(): void
    {
        $ids = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_admin_sso']"
            . "/group[@id='azure']/depends/field/@id"
        );

        $paths = [];
        foreach ($ids as $attr) {
            $paths[] = $attr->value;
        }

        self::assertContains('magedevgroup_admin_sso/general/enabled', $paths);
        self::assertContains('magedevgroup_admin_sso/general/active_provider', $paths);
        foreach ($paths as $path) {
            self::assertSame(3, count(explode('/', $path)), "Depend '$path' is not fully qualified.");
        }
    }

    /**
     * The declared field path is the one the preset reads: a tenant stored at that
     * path lands in the Entra discovery URL, proving the config field feeds the preset.
     */
    public function testConfiguredTenantFlowsIntoDiscoveryUrl(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string =>
                $path === Config::XML_PATH_TENANT ? '11111111-2222-3333-4444-555555555555' : null
        );
        $assetRepository = $this->createStub(AssetRepository::class);

        $preset = new EntraPreset(new Config($scopeConfig), $assetRepository);

        self::assertSame(
            'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555'
            . '/v2.0/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl([])
        );
    }
}
