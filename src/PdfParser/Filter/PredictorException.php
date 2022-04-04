<?php

/**
 * This file is part of FPDI
 *
 * @package   setasign\Fpdi
 * @copyright Copyright (c) 2022 Yan-Jie Wang (ubzeme@gmail.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace setasign\Fpdi\PdfParser\Filter;

/**
 * Exception for Predict filter class
 */
class PredictorException extends FilterException
{
    /**
     * @var integer
     */
    const PREDICTOR_NOT_IMPLEMENTED = 0x0601;

    /**
     * @var integer
     */
    const PREDICTOR_NOT_SUPPORT = 0x0601;
}

