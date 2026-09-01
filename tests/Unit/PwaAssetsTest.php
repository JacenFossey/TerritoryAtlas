<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

class PwaAssetsTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_manifest_describes_an_installable_application_with_valid_icons(): void
    {
        $publicPath = dirname(__DIR__, 2).'/public';
        $manifestPath = $publicPath.'/manifest.webmanifest';

        $this->assertFileExists($manifestPath);

        $manifestContents = file_get_contents($manifestPath);

        $this->assertIsString($manifestContents);

        $manifest = json_decode($manifestContents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('TerritoryAtlas', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        $iconsBySize = array_column($manifest['icons'], null, 'sizes');

        $this->assertSame('/icons/app-icon-192.png', $iconsBySize['192x192']['src']);
        $this->assertSame('/icons/app-icon-512.png', $iconsBySize['512x512']['src']);

        foreach ([192, 512] as $size) {
            $iconPath = $publicPath.$iconsBySize["{$size}x{$size}"]['src'];

            $this->assertFileExists($iconPath);

            $dimensions = getimagesize($iconPath);

            $this->assertIsArray($dimensions);
            $this->assertSame($size, $dimensions[0]);
            $this->assertSame($size, $dimensions[1]);
            $this->assertSame('image/png', $dimensions['mime']);
        }
    }

    public function test_service_worker_asset_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/public/sw.js');
    }
}
