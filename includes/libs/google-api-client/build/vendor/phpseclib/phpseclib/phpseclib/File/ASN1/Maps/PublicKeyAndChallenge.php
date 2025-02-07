<?php

/**
 * PublicKeyAndChallenge
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
 * PublicKeyAndChallenge
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class PublicKeyAndChallenge
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['spki' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\SubjectPublicKeyInfo::MAP, 'challenge' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_IA5_STRING]]];
}
