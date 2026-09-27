<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrImage
{
    /** Data URI SVG, dùng trực tiếp trong <img src>. */
    public static function dataUri(string $content): string
    {
        return (new QRCode(new QROptions([
            'outputBase64' => true,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'drawLightModules' => false,
        ])))->render($content);
    }
}
