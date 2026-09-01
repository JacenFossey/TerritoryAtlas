<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeographyAssetController extends Controller
{
    private const FILES = [
        'common-places.geojson',
        'lower-tier.geojson',
        'upper-single-tier.geojson',
    ];

    public function __invoke(string $filename): BinaryFileResponse
    {
        abort_unless(in_array($filename, self::FILES, true), 404);

        return response()->file(public_path("geo/{$filename}"), [
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=86400',
            'Content-Type' => 'application/geo+json',
        ]);
    }
}
