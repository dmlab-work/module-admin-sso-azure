<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit\Plugin;

use DmLab\AdminSsoAzure\Model\Config;
use DmLab\AdminSsoAzure\Model\EntraPreset;
use DmLab\AdminSsoAzure\Plugin\EntraGroupsOverage;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;

class EntraGroupsOverageTest extends TestCase
{
    /** @var EntraPreset real Entra preset */
    private EntraPreset $preset;

    protected function setUp(): void
    {
        $this->preset = new EntraPreset(
            new Config($this->createStub(ScopeConfigInterface::class)),
            $this->createStub(AssetRepository::class)
        );
    }

    /**
     * @param array<string,mixed> $claims
     * @return array<string,mixed>
     */
    private function apply(array $claims, ?ProviderPresetInterface $preset = null): array
    {
        $preset ??= $this->preset;
        [$result, $passedPreset] = (new EntraGroupsOverage())
            ->beforeCreate($this->createStub(IdentityFactory::class), $claims, $preset);
        self::assertSame($preset, $passedPreset);

        return $result;
    }

    public function testHasGroupsWithoutGroupsIsFlaggedAsDistributed(): void
    {
        $claims = $this->apply(['sub' => 's', 'hasgroups' => true]);

        self::assertSame(['groups' => 'hasgroups'], $claims['_claim_names']);
    }

    public function testExistingClaimNamesAreKept(): void
    {
        $claims = $this->apply(['hasgroups' => 'true', '_claim_names' => ['roles' => 'src2']]);

        self::assertSame(['roles' => 'src2', 'groups' => 'hasgroups'], $claims['_claim_names']);
    }

    public function testFlagFeedsTheIdentityDistributedGroups(): void
    {
        $identity = (new IdentityFactory())->create(
            $this->apply(['sub' => 's', 'iss' => 'https://login.microsoftonline.com/t/v2.0', 'hasgroups' => true]),
            $this->preset
        );

        self::assertTrue($identity->hasDistributedGroups());
    }

    public function testClaimsUntouchedWithoutOverage(): void
    {
        $claims = ['sub' => 's', 'groups' => ['g1']];

        self::assertSame($claims, $this->apply($claims));
        self::assertSame(['sub' => 's', 'hasgroups' => false], $this->apply(['sub' => 's', 'hasgroups' => false]));
        self::assertSame(
            ['hasgroups' => true, 'groups' => ['g1']],
            $this->apply(['hasgroups' => true, 'groups' => ['g1']])
        );
    }

    public function testOtherPresetsAreIgnored(): void
    {
        $claims = ['sub' => 's', 'hasgroups' => true];

        self::assertSame($claims, $this->apply($claims, $this->createStub(ProviderPresetInterface::class)));
    }
}
