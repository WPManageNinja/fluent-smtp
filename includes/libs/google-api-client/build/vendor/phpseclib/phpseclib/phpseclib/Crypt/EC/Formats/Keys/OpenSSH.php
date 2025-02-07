<?php

/**
 * OpenSSH Formatted EC Key Handler
 *
 * PHP version 5
 *
 * Place in $HOME/.ssh/authorized_keys
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Keys;

use FluentSmtpLib\phpseclib3\Common\Functions\Strings;
use FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Keys\OpenSSH as Progenitor;
use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Base as BaseCurve;
use FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519;
use FluentSmtpLib\phpseclib3\Exception\UnsupportedCurveException;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * OpenSSH Formatted EC Key Handler
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class OpenSSH extends \FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Keys\OpenSSH
{
    use Common;
    /**
     * Supported Key Types
     *
     * @var array
     */
    protected static $types = ['ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521', 'ssh-ed25519'];
    /**
     * Break a public or private key down into its constituent components
     *
     * @param string $key
     * @param string $password optional
     * @return array
     */
    public static function load($key, $password = '')
    {
        $parsed = parent::load($key, $password);
        if (isset($parsed['paddedKey'])) {
            $paddedKey = $parsed['paddedKey'];
            list($type) = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::unpackSSH2('s', $paddedKey);
            if ($type != $parsed['type']) {
                throw new \RuntimeException("The public and private keys are not of the same type ({$type} vs {$parsed['type']})");
            }
            if ($type == 'ssh-ed25519') {
                list(, $key, $comment) = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::unpackSSH2('sss', $paddedKey);
                $key = \FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Keys\libsodium::load($key);
                $key['comment'] = $comment;
                return $key;
            }
            list($curveName, $publicKey, $privateKey, $comment) = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::unpackSSH2('ssis', $paddedKey);
            $curve = self::loadCurveByParam(['namedCurve' => $curveName]);
            $curve->rangeCheck($privateKey);
            return ['curve' => $curve, 'dA' => $privateKey, 'QA' => self::extractPoint("\x00{$publicKey}", $curve), 'comment' => $comment];
        }
        if ($parsed['type'] == 'ssh-ed25519') {
            if (\FluentSmtpLib\phpseclib3\Common\Functions\Strings::shift($parsed['publicKey'], 4) != "\x00\x00\x00 ") {
                throw new \RuntimeException('Length of ssh-ed25519 key should be 32');
            }
            $curve = new \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519();
            $qa = self::extractPoint($parsed['publicKey'], $curve);
        } else {
            list($curveName, $publicKey) = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::unpackSSH2('ss', $parsed['publicKey']);
            $curveName = '\\FluentSmtpLib\\phpseclib3\\Crypt\\EC\\Curves\\' . $curveName;
            $curve = new $curveName();
            $qa = self::extractPoint("\x00" . $publicKey, $curve);
        }
        return ['curve' => $curve, 'QA' => $qa, 'comment' => $parsed['comment']];
    }
    /**
     * Returns the alias that corresponds to a curve
     *
     * @return string
     */
    private static function getAlias(\FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Base $curve)
    {
        self::initialize_static_variables();
        $reflect = new \ReflectionClass($curve);
        $name = $reflect->getShortName();
        $oid = self::$curveOIDs[$name];
        $aliases = \array_filter(self::$curveOIDs, function ($v) use($oid) {
            return $v == $oid;
        });
        $aliases = \array_keys($aliases);
        for ($i = 0; $i < \count($aliases); $i++) {
            if (\in_array('ecdsa-sha2-' . $aliases[$i], self::$types)) {
                $alias = $aliases[$i];
                break;
            }
        }
        if (!isset($alias)) {
            throw new \FluentSmtpLib\phpseclib3\Exception\UnsupportedCurveException($name . ' is not a curve that the OpenSSH plugin supports');
        }
        return $alias;
    }
    /**
     * Convert an EC public key to the appropriate format
     *
     * @param BaseCurve $curve
     * @param \phpseclib3\Math\Common\FiniteField\Integer[] $publicKey
     * @param array $options optional
     * @return string
     */
    public static function savePublicKey(\FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Base $curve, array $publicKey, array $options = [])
    {
        $comment = isset($options['comment']) ? $options['comment'] : self::$comment;
        if ($curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519) {
            $key = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('ss', 'ssh-ed25519', $curve->encodePoint($publicKey));
            if (isset($options['binary']) ? $options['binary'] : self::$binary) {
                return $key;
            }
            $key = 'ssh-ed25519 ' . \base64_encode($key) . ' ' . $comment;
            return $key;
        }
        $alias = self::getAlias($curve);
        $points = "\x04" . $publicKey[0]->toBytes() . $publicKey[1]->toBytes();
        $key = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('sss', 'ecdsa-sha2-' . $alias, $alias, $points);
        if (isset($options['binary']) ? $options['binary'] : self::$binary) {
            return $key;
        }
        $key = 'ecdsa-sha2-' . $alias . ' ' . \base64_encode($key) . ' ' . $comment;
        return $key;
    }
    /**
     * Convert a private key to the appropriate format.
     *
     * @param BigInteger $privateKey
     * @param Ed25519 $curve
     * @param \phpseclib3\Math\Common\FiniteField\Integer[] $publicKey
     * @param string $secret optional
     * @param string $password optional
     * @param array $options optional
     * @return string
     */
    public static function savePrivateKey(\FluentSmtpLib\phpseclib3\Math\BigInteger $privateKey, \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Base $curve, array $publicKey, $secret = null, $password = '', array $options = [])
    {
        if ($curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519) {
            if (!isset($secret)) {
                throw new \RuntimeException('Private Key does not have a secret set');
            }
            if (\strlen($secret) != 32) {
                throw new \RuntimeException('Private Key secret is not of the correct length');
            }
            $pubKey = $curve->encodePoint($publicKey);
            $publicKey = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('ss', 'ssh-ed25519', $pubKey);
            $privateKey = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('sss', 'ssh-ed25519', $pubKey, $secret . $pubKey);
            return self::wrapPrivateKey($publicKey, $privateKey, $password, $options);
        }
        $alias = self::getAlias($curve);
        $points = "\x04" . $publicKey[0]->toBytes() . $publicKey[1]->toBytes();
        $publicKey = self::savePublicKey($curve, $publicKey, ['binary' => \true]);
        $privateKey = \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('sssi', 'ecdsa-sha2-' . $alias, $alias, $points, $privateKey);
        return self::wrapPrivateKey($publicKey, $privateKey, $password, $options);
    }
}
