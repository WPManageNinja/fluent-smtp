<?php

/**
 * DssSigValue
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\File\ASN1\Maps;

use FluentSmtpLib\phpseclib3\File\ASN1;
/**
 * DssSigValue
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class DssSigValue
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['r' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER], 's' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER]]];
}
