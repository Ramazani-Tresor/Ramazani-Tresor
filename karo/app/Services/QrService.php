<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes générés localement en SVG (aucun service externe). */
class QrService
{
    public function svg(string $content, int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($content);
    }

    public function dataUri(string $content, int $size = 160): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($content, $size));
    }
}
