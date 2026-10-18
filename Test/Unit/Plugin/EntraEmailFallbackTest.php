<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit\Plugin;

use DmLab\AdminSsoAzure\Model\Config;
use DmLab\AdminSsoAzure\Model\EntraPreset;
use DmLab\AdminSsoAzure\Plugin\EntraEmailFallback;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntraEmailFallbackTest extends TestCase
{
    /** @var EntraPreset real Entra preset passed as the plugin subject's preset. */
    private EntraPreset $preset;

    /** @var EntraEmailFallback plugin under test, over Magento's real email validator. */
    private EntraEmailFallback $plugin;

    protected function setUp(): void
    {
        $this->preset = new EntraPreset(
            new Config($this->createStub(ScopeConfigInterface::class)),
            $this->createStub(AssetRepository::class)
        );

        $this->plugin = new EntraEmailFallback(new EmailAddress());
    }

    /**
     * @param array<string,mixed> $claims
     * @return array<string,mixed>
     */
    private function apply(array $claims, ?ProviderPresetInterface $preset = null): array
    {
        $preset ??= $this->preset;
        $result = $this->plugin->beforeCreate($this->createStub(IdentityFactory::class), $claims, $preset);

        self::assertCount(2, $result);
        self::assertSame($preset, $result[1]);

        return $result[0];
    }

    public function testPresentEmailLeavesClaimsUntouched(): void
    {
        $claims = [
            'email' => 'jane@contoso.com',
            'preferred_username' => 'jane.upn@contoso.com',
            'xms_edov' => true,
        ];

        self::assertSame($claims, $this->apply($claims));
    }

    public function testPreferredUsernameFillsMissingEmailAndDropsXmsEdov(): void
    {
        $result = $this->apply([
            'preferred_username' => 'jane@contoso.com',
            'upn' => 'other@contoso.com',
            'xms_edov' => true,
        ]);

        self::assertSame('jane@contoso.com', $result['email']);
        self::assertArrayNotHasKey('xms_edov', $result);
        self::assertFalse($this->preset->resolveEmailVerified($result));
    }

    public function testUpnUsedWhenPreferredUsernameMissing(): void
    {
        $result = $this->apply(['upn' => 'jane@contoso.com', 'xms_edov' => '1']);

        self::assertSame('jane@contoso.com', $result['email']);
        self::assertArrayNotHasKey('xms_edov', $result);
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function blankEmails(): array
    {
        return [
            'empty string' => [''],
            'whitespace' => ['   '],
            'null' => [null],
            'array' => [['jane@contoso.com']],
            'bool' => [true],
        ];
    }

    #[DataProvider('blankEmails')]
    public function testBlankOrNonScalarEmailTreatedAsMissing(mixed $email): void
    {
        $result = $this->apply(['email' => $email, 'preferred_username' => 'jane@contoso.com']);

        self::assertSame('jane@contoso.com', $result['email']);
    }

    public function testInvalidAndNonScalarCandidatesAreSkipped(): void
    {
        $result = $this->apply([
            'preferred_username' => ['jane@contoso.com'],
            'upn' => 'jane@contoso.com',
        ]);
        self::assertSame('jane@contoso.com', $result['email']);

        $result = $this->apply([
            'preferred_username' => 'not-an-email',
            'upn' => 'jane@contoso.com',
        ]);
        self::assertSame('jane@contoso.com', $result['email']);

        $result = $this->apply([
            'preferred_username' => 'jane@contoso',
            'upn' => true,
        ]);
        self::assertArrayNotHasKey('email', $result);
    }

    public function testCandidateIsTrimmed(): void
    {
        $result = $this->apply(['preferred_username' => ' jane@contoso.com ']);

        self::assertSame('jane@contoso.com', $result['email']);
    }

    public function testNothingUsableLeavesEmailAbsent(): void
    {
        $result = $this->apply([
            'email' => ' ',
            'preferred_username' => 'not-an-email',
            'upn' => ['x@y.z'],
            'xms_edov' => true,
        ]);

        self::assertArrayNotHasKey('email', $result);
        self::assertArrayNotHasKey('xms_edov', $result);
    }

    public function testNonEntraPresetClaimsAreIdentical(): void
    {
        $claims = ['preferred_username' => 'jane@contoso.com', 'xms_edov' => true];

        self::assertSame($claims, $this->apply($claims, $this->createStub(ProviderPresetInterface::class)));
    }
}
