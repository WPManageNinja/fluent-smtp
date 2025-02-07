<?php

/**
 * CertificationRequest
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
 * CertificationRequest
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class CertificationRequest
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['certificationRequestInfo' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\CertificationRequestInfo::MAP, 'signatureAlgorithm' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\AlgorithmIdentifier::MAP, 'signature' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_BIT_STRING]]];
}
