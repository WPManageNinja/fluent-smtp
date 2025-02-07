<?php

/**
 * PKCS#1 Formatted DSA Key Handler
 *
 * PHP version 5
 *
 * Used by File/X509.php
 *
 * Processes keys with the following headers:
 *
 * -----BEGIN DSA PRIVATE KEY-----
 * -----BEGIN DSA PUBLIC KEY-----
 * -----BEGIN DSA PARAMETERS-----
 *
 * Analogous to ssh-keygen's pem format (as specified by -m)
 *
 * Also, technically, PKCS1 decribes RSA but I am not aware of a formal specification for DSA.
 * The DSA private key format seems to have been adapted from the RSA private key format so
 * we're just re-using that as the name.
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\DSA\Formats\Keys;

use FluentSmtpLib\phpseclib3\Common\Functions\Strings;
use FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Keys\PKCS1 as Progenitor;
use FluentSmtpLib\phpseclib3\File\ASN1;
use FluentSmtpLib\phpseclib3\File\ASN1\Maps;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * PKCS#1 Formatted DSA Key Handler
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
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::asn1map($decoded[0], \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAParams::MAP);
        if (\is_array($key)) {
            return $key;
        }
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::asn1map($decoded[0], \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAPrivateKey::MAP);
        if (\is_array($key)) {
            return $key;
        }
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::asn1map($decoded[0], \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAPublicKey::MAP);
        if (\is_array($key)) {
            return $key;
        }
        throw new \RuntimeException('Unable to perform ASN1 mapping');
    }
    /**
     * Convert DSA parameters to the appropriate format
     *
     * @param BigInteger $p
     * @param BigInteger $q
     * @param BigInteger $g
     * @return string
     */
    public static function saveParameters(\FluentSmtpLib\phpseclib3\Math\BigInteger $p, \FluentSmtpLib\phpseclib3\Math\BigInteger $q, \FluentSmtpLib\phpseclib3\Math\BigInteger $g)
    {
        $key = ['p' => $p, 'q' => $q, 'g' => $g];
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::encodeDER($key, \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAParams::MAP);
        return "-----BEGIN DSA PARAMETERS-----\r\n" . \chunk_split(\FluentSmtpLib\phpseclib3\Common\Functions\Strings::base64_encode($key), 64) . "-----END DSA PARAMETERS-----\r\n";
    }
    /**
     * Convert a private key to the appropriate format.
     *
     * @param BigInteger $p
     * @param BigInteger $q
     * @param BigInteger $g
     * @param BigInteger $y
     * @param BigInteger $x
     * @param string $password optional
     * @param array $options optional
     * @return string
     */
    public static function savePrivateKey(\FluentSmtpLib\phpseclib3\Math\BigInteger $p, \FluentSmtpLib\phpseclib3\Math\BigInteger $q, \FluentSmtpLib\phpseclib3\Math\BigInteger $g, \FluentSmtpLib\phpseclib3\Math\BigInteger $y, \FluentSmtpLib\phpseclib3\Math\BigInteger $x, $password = '', array $options = [])
    {
        $key = ['version' => 0, 'p' => $p, 'q' => $q, 'g' => $g, 'y' => $y, 'x' => $x];
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::encodeDER($key, \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAPrivateKey::MAP);
        return self::wrapPrivateKey($key, 'DSA', $password, $options);
    }
    /**
     * Convert a public key to the appropriate format
     *
     * @param BigInteger $p
     * @param BigInteger $q
     * @param BigInteger $g
     * @param BigInteger $y
     * @return string
     */
    public static function savePublicKey(\FluentSmtpLib\phpseclib3\Math\BigInteger $p, \FluentSmtpLib\phpseclib3\Math\BigInteger $q, \FluentSmtpLib\phpseclib3\Math\BigInteger $g, \FluentSmtpLib\phpseclib3\Math\BigInteger $y)
    {
        $key = \FluentSmtpLib\phpseclib3\File\ASN1::encodeDER($y, \FluentSmtpLib\phpseclib3\File\ASN1\Maps\DSAPublicKey::MAP);
        return self::wrapPublicKey($key, 'DSA');
    }
}
