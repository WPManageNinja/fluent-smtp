<?php

/**
 * "PKCS1" Formatted EC Key Handler
 *
 * PHP version 5
 *
 * Processes keys with the following headers:
 *
 * -----BEGIN DH PARAMETERS-----
 *
 * Technically, PKCS1 is for RSA keys, only, but we're using PKCS1 to describe
 * DSA, whose format isn't really formally described anywhere, so might as well
 * use it to describe this, too.
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\DH\Formats\Keys;

use FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Keys\PKCS1 as Progenitor;
use FluentSmtpLib\phpseclib3\File\ASN1;
use FluentSmtpLib\phpseclib3\File\ASN1\Maps;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * "PKCS1" Formatted DH Key Handler
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class PKCS1 extends \FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Keys\PKCS1
{
    /**
     * Break a public or private key down into its constituent components
     *
     * @param string $key
     * @param string $password optional
     * @return array
     */
    public static function load($key, $password = '')
    {
        $key = parent::load($key, $password);
        $decoded = \FluentSmtpLib\phpseclib3\File\ASN1::decodeBER($key);
        if (!$decoded) {
            throw new \RuntimeException('Unable to decode BER');
        }
        $components = \FluentSmtpLib\phpseclib3\File\ASN1::asn1map($decoded[0], \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DHParameter::MAP);
        if (!\is_array($components)) {
            throw new \RuntimeException('Unable to perform ASN1 mapping on parameters');
        }
        return $components;
    }
    /**
     * Convert EC parameters to the appropriate format
     *
     * @return string
     */
    public static function saveParameters(\FluentSmtpLib\phpseclib3\Math\BigInteger $prime, \FluentSmtpLib\phpseclib3\Math\BigInteger $base, array $options = [])
    {
        $params = ['prime' => $prime, 'base' => $base];
        $params = \FluentSmtpLib\phpseclib3\File\ASN1::encodeDER($params, \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DHParameter::MAP);
        return "-----BEGIN DH PARAMETERS-----\r\n" . \chunk_split(\base64_encode($params), 64) . "-----END DH PARAMETERS-----\r\n";
    }
}
