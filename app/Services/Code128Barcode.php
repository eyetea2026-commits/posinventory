<?php

namespace App\Services;

/**
 * Code 128 barcodes drawn as SVG. Every character is encoded in code set B
 * (never set C): the store's scanner drops the last character of set C
 * codes, so set B is what scans reliably.
 */
class Code128Barcode
{
    /**
     * Bar/space widths (in modules) of every Code 128 symbol, 0-106.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * Widths of the alternating bars and spaces, starting with a bar.
     *
     * @return list<int>
     */
    public static function widths(string $text): array
    {
        $values = [self::START_B];
        foreach (str_split($text) as $character) {
            $value = ord($character) - 32;
            if ($value < 0 || $value > 95) {
                throw new \InvalidArgumentException("Code 128 set B cannot encode \"{$character}\".");
            }
            $values[] = $value;
        }

        $checksum = self::START_B;
        foreach (array_slice($values, 1) as $position => $value) {
            $checksum += $value * ($position + 1);
        }
        $values[] = $checksum % 103;
        $values[] = self::STOP;

        $widths = [];
        foreach ($values as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $width) {
                $widths[] = (int) $width;
            }
        }

        return $widths;
    }

    /**
     * An SVG of the barcode, $quietModules of white on each side.
     */
    public static function svg(string $text, int $height = 40, int $quietModules = 10): string
    {
        $x = $quietModules;
        $rects = '';
        foreach (self::widths($text) as $index => $width) {
            if ($index % 2 === 0) {
                $rects .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="'.$height.'"/>';
            }
            $x += $width;
        }
        $totalWidth = $x + $quietModules;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$totalWidth.'" height="'.$height.'" viewBox="0 0 '.$totalWidth.' '.$height.'" shape-rendering="crispEdges">'
            .'<rect x="0" y="0" width="'.$totalWidth.'" height="'.$height.'" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';
    }

    /**
     * The SVG as a data: URI, for an <img> in a PDF.
     */
    public static function dataUri(string $text, int $height = 40): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $height));
    }
}
