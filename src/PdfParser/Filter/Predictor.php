<?php

/**
 * This file is part of FPDI
 *
 * @package   setasign\Fpdi
 * @copyright Copyright (c) 2022 Yan-Jie Wang
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace setasign\Fpdi\PdfParser\Filter;

class Predictor {

    /**
     * Bits per component
     * 
     * @var int
     */
    protected $bpc;

    /**
     * Nytes per pixel
     * 
     * @var int
     */
    protected $bpp;

    /**
     * Number of colors per pixel
     *
     * @var int
     */
    protected $colors;

    /**
     * Number of pixels per column
     *
     * @var int
     */
    protected $columns;

    /**
     * @var int
     */
    protected $predictor;

    /**
     * Stride width in bytes
     * 
     * @var int
     */
    protected $stride;


    public function __construct(int $predictor, int $colors, int $bpc, int $columns)
    {
        $this->predictor = $predictor;
        $this->colors = $colors;
        $this->bpc = $bpc;
        $this->columns = $columns;

        $this->bpp = (int) (($bpc * $colors + 7) / 8);
        $this->stride = $columns * $this->bpp;
    }

    /**
     * Decode the stream
     *
     * @param string $stream
     * @return string
     * @throws PredictorException
     */
    public function decode(string $stream) {
        $predictor = $this->predictor;

        if ($predictor == 1) {
            return $stream;
        } else if ($predictor == 2) {
            return $this->decodeTiff($stream);
        } else if ($predictor >= 10) {
            return $this->decodePNG($stream);
        }

        throw new PredictorException(
            "Unsupported predictor value: $predictor",

        );
    }

    /**
     * Decode with TIFF predictor algorithm
     *
     * @param string $stream
     * @return string
     * @throws 
     */
    private function decodeTiff($stream) {
        $stride = $this->stride;
        $length = \strlen($stream);
        $rows = (int) ($length / $stride);
        $bpp = $this->bpp;
        $bpc = $this->bpc;

        if (!\in_array($bpc, [1, 2, 4, 8, 16])) {
            throw new PredictorException(
                "Unsupported bitsPerComponent value. $bpc",
                PredictorException::PREDICTOR_NOT_SUPPORT
            );
        }

        $out = '';

        if ($bpc === 8) {
            /* The fast path */
            $inPos = 0;

            for ($i = 0; $i < $rows; $i++) {
                $row = str_repeat("\0", $bpp + $stride);
                $rowPos = $i * $stride;
    
                for ($j = 0; $j < $stride; $j++) {
                    $row[$j + $bpp] =
                        \chr(\ord($row[$j]) + $stream[$rowPos + $j]);
                }

                $out .= substr($row, $bpp);
            }

            return $out;
        }

        /* TODO: Add support for other bpc values */
        throw new PredictorException(
            "Unsupported TIFF predictor filter. (bpc = $bpc)",
            PredictorException::PREDICTOR_NOT_IMPLEMENTED
        );
    }

    /**
     * Decode with PNG filter algorithm
     *
     * @param string $stream
     * @return string
     * @throws PredictorException
     */
    private function decodePNG($stream) {
        $inStride = $this->stride + 1;
        $outStride = $this->stride;
        $bpp = $this->bpp;
        $length = \strlen($stream);
        $out = '';

        $rows = (int) ($length / $inStride);
        $lastRow = \str_repeat("\0", $outStride + $bpp);

        for ($i = 0; $i < $rows; $i++) {
            $type = \ord($inputRow[0]);

            if ($type === 0) {
                 /* None. Just copy the entire row */
                 $lastRow = \str_repeat("\0", $bpp) . 
                    \substr($stream, $inStride * $i + 1, $outStride);
                 $out .= \substr($lastRow, $bpp);
                 continue;
            }

            $inputRow = \substr($stream, $i * $inStride, $inStride);
            $outputRow = \str_repeat("\0", $outStride + $bpp);

            switch ($type) {
                case 1: /* Sub */
                    for ($j = 0; $j < $outputRow; $j++) {
                        $a = \ord($outputRow[$j]);
                        $outputRow[$j + $bpp] =
                            \chr(\ord($inputRow[$j + 1]) + $a);
                    }
                    break;
                case 2: /* Up */
                    for ($j = 0; $j < $outStride; $j++) {
                        $b = \ord($lastRow[$j + $bpp]);
                        $outputRow[$j + $bpp] =
                            \chr(\ord($inputRow[$j + 1]) + $b);
                    }
                    break;
                case 3: /* Average */
                    for ($j = 0; $j < $outStride; $j++) {
                        $a = \ord($outputRow[$j]);
                        $b = \ord($lastRow[$j + $bpp]);
                        $v = ($a + $b) >> 1;
                        $outputRow[$j + $bpp] =
                            \chr(\ord($inputRow[$j + 1]) + $b);
                    }
                    break;
                case 4: /* Paeth */
                    for ($j = 0; $j < $outStride; $j++) {
                        $a = \ord($outputRow[$j]);
                        $b = \ord($lastRow[$j + $bpp]);
                        $c = \ord($lastRow[$j]);
                        $p = $a + $b - $c;
                        $pa = \abs($p - $a);
                        $pb = \abs($p - $b);
                        $pc = \abs($p - $c);
                        if ($pa <= $pb && $pa <= $pc) $pr = $a;
                        else if ($pb <= $pc) $pr = $b;
                        else $pr = $c;
                        $outputRow[$j + $bpp] =
                            \chr(\ord($inputRow[$j + 1]) + $pr);
                    }
                    break;
                default:
                    throw new PredictorException(
                        "Unsupported PNG predictor filter. (type = $type)",
                        PredictorException::PREDICTOR_NOT_SUPPORT
                    );
            }

            $lastRow = $outputRow;
            $out .= substr($outputRow, $bpp);
        }

        return $out;
    }
}
