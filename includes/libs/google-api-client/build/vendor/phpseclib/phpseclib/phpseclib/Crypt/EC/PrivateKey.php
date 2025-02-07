<?php

/**
 * EC Private Key
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC;

use FluentSmtpLib\phpseclib3\Common\Functions\Strings;
use FluentSmtpLib\phpseclib3\Crypt\Common;
use FluentSmtpLib\phpseclib3\Crypt\EC;
use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery as MontgomeryCurve;
use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\TwistedEdwards as TwistedEdwardsCurve;
use FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve25519;
use FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519;
use FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Keys\PKCS1;
use FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Signature\ASN1 as ASN1Signature;
use FluentSmtpLib\phpseclib3\Crypt\Hash;
use FluentSmtpLib\phpseclib3\Exception\UnsupportedOperationException;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * EC Private Key
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
final class PrivateKey extends \FluentSmtpLib\phpseclib3\Crypt\EC implements \FluentSmtpLib\phpseclib3\Crypt\Common\PrivateKey
{
    use Common\Traits\PasswordProtected;
    /**
     * Private Key dA
     *
     * sign() converts this to a BigInteger so one might wonder why this is a FiniteFieldInteger instead of
     * a BigInteger. That's because a FiniteFieldInteger, when converted to a byte string, is null padded by
     * a certain amount whereas a BigInteger isn't.
     *
     * @var object
     */
    protected $dA;
    /**
     * @var string
     */
    protected $secret;
    /**
     * Multiplies an encoded point by the private key
     *
     * Used by ECDH
     *
     * @param string $coordinates
     * @return string
     */
    public function multiply($coordinates)
    {
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery) {
            if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve25519 && self::$engines['libsodium']) {
                return \sodium_crypto_scalarmult($this->dA->toBytes(), $coordinates);
            }
            $point = [$this->curve->convertInteger(new \FluentSmtpLib\phpseclib3\Math\BigInteger(\strrev($coordinates), 256))];
            $point = $this->curve->multiplyPoint($point, $this->dA);
            return \strrev($point[0]->toBytes(\true));
        }
        if (!$this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\TwistedEdwards) {
            $coordinates = "\x00{$coordinates}";
        }
        $point = \FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Keys\PKCS1::extractPoint($coordinates, $this->curve);
        $point = $this->curve->multiplyPoint($point, $this->dA);
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\TwistedEdwards) {
            return $this->curve->encodePoint($point);
        }
        if (empty($point)) {
            throw new \RuntimeException('The infinity point is invalid');
        }
        return "\x04" . $point[0]->toBytes(\true) . $point[1]->toBytes(\true);
    }
    /**
     * Create a signature
     *
     * @see self::verify()
     * @param string $message
     * @return mixed
     */
    public function sign($message)
    {
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery) {
            throw new \FluentSmtpLib\phpseclib3\Exception\UnsupportedOperationException('Montgomery Curves cannot be used to create signatures');
        }
        $dA = $this->dA;
        $order = $this->curve->getOrder();
        $shortFormat = $this->shortFormat;
        $format = $this->sigFormat;
        if ($format === \false) {
            return \false;
        }
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\TwistedEdwards) {
            if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519 && self::$engines['libsodium'] && !isset($this->context)) {
                $result = \sodium_crypto_sign_detached($message, $this->withPassword()->toString('libsodium'));
                return $shortFormat == 'SSH2' ? \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('ss', 'ssh-' . \strtolower($this->getCurve()), $result) : $result;
            }
            // contexts (Ed25519ctx) are supported but prehashing (Ed25519ph) is not.
            // quoting https://tools.ietf.org/html/rfc8032#section-8.5 ,
            // "The Ed25519ph and Ed448ph variants ... SHOULD NOT be used"
            $A = $this->curve->encodePoint($this->QA);
            $curve = $this->curve;
            $hash = new \FluentSmtpLib\phpseclib3\Crypt\Hash($curve::HASH);
            $secret = \substr($hash->hash($this->secret), $curve::SIZE);
            if ($curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Ed25519) {
                $dom = !isset($this->context) ? '' : 'SigEd25519 no Ed25519 collisions' . "\x00" . \chr(\strlen($this->context)) . $this->context;
            } else {
                $context = isset($this->context) ? $this->context : '';
                $dom = 'SigEd448' . "\x00" . \chr(\strlen($context)) . $context;
            }
            // SHA-512(dom2(F, C) || prefix || PH(M))
            $r = $hash->hash($dom . $secret . $message);
            $r = \strrev($r);
            $r = new \FluentSmtpLib\phpseclib3\Math\BigInteger($r, 256);
            list(, $r) = $r->divide($order);
            $R = $curve->multiplyPoint($curve->getBasePoint(), $r);
            $R = $curve->encodePoint($R);
            $k = $hash->hash($dom . $R . $A . $message);
            $k = \strrev($k);
            $k = new \FluentSmtpLib\phpseclib3\Math\BigInteger($k, 256);
            list(, $k) = $k->divide($order);
            $S = $k->multiply($dA)->add($r);
            list(, $S) = $S->divide($order);
            $S = \str_pad(\strrev($S->toBytes()), $curve::SIZE, "\x00");
            return $shortFormat == 'SSH2' ? \FluentSmtpLib\phpseclib3\Common\Functions\Strings::packSSH2('ss', 'ssh-' . \strtolower($this->getCurve()), $R . $S) : $R . $S;
        }
        if (self::$engines['OpenSSL'] && \in_array($this->hash->getHash(), \openssl_get_md_methods())) {
            $signature = '';
            // altho PHP's OpenSSL bindings only supported EC key creation in PHP 7.1 they've long
            // supported signing / verification
            // we use specified curves to avoid issues with OpenSSL possibly not supporting a given named curve;
            // doing this may mean some curve-specific optimizations can't be used but idk if OpenSSL even
            // has curve-specific optimizations
            $result = \openssl_sign($message, $signature, $this->withPassword()->toString('PKCS8', ['namedCurve' => \false]), $this->hash->getHash());
            if ($result) {
                if ($shortFormat == 'ASN1') {
                    return $signature;
                }
                \extract(\FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Signature\ASN1::load($signature));
                return $this->formatSignature($r, $s);
            }
        }
        $e = $this->hash->hash($message);
        $e = new \FluentSmtpLib\phpseclib3\Math\BigInteger($e, 256);
        $Ln = $this->hash->getLength() - $order->getLength();
        $z = $Ln > 0 ? $e->bitwise_rightShift($Ln) : $e;
        while (\true) {
            $k = \FluentSmtpLib\phpseclib3\Math\BigInteger::randomRange(self::$one, $order->subtract(self::$one));
            list($x, $y) = $this->curve->multiplyPoint($this->curve->getBasePoint(), $k);
            $x = $x->toBigInteger();
            list(, $r) = $x->divide($order);
            if ($r->equals(self::$zero)) {
                continue;
            }
            $kinv = $k->modInverse($order);
            $temp = $z->add($dA->multiply($r));
            $temp = $kinv->multiply($temp);
            list(, $s) = $temp->divide($order);
            if (!$s->equals(self::$zero)) {
                break;
            }
        }
        // the following is an RFC6979 compliant implementation of deterministic ECDSA
        // it's unused because it's mainly intended for use when a good CSPRNG isn't
        // available. if phpseclib's CSPRNG isn't good then even key generation is
        // suspect
        /*
        // if this were actually being used it'd probably be better if this lived in load() and createKey()
        $this->q = $this->curve->getOrder();
        $dA = $this->dA->toBigInteger();
        $this->x = $dA;
        
        $h1 = $this->hash->hash($message);
        $k = $this->computek($h1);
        list($x, $y) = $this->curve->multiplyPoint($this->curve->getBasePoint(), $k);
        $x = $x->toBigInteger();
        list(, $r) = $x->divide($this->q);
        $kinv = $k->modInverse($this->q);
        $h1 = $this->bits2int($h1);
        $temp = $h1->add($dA->multiply($r));
        $temp = $kinv->multiply($temp);
        list(, $s) = $temp->divide($this->q);
        */
        return $this->formatSignature($r, $s);
    }
    /**
     * Returns the private key
     *
     * @param string $type
     * @param array $options optional
     * @return string
     */
    public function toString($type, array $options = [])
    {
        $type = self::validatePlugin('Keys', $type, 'savePrivateKey');
        return $type::savePrivateKey($this->dA, $this->curve, $this->QA, $this->secret, $this->password, $options);
    }
    /**
     * Returns the public key
     *
     * @see self::getPrivateKey()
     * @return mixed
     */
    public function getPublicKey()
    {
        $format = 'PKCS8';
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery) {
            $format = 'MontgomeryPublic';
        }
        $type = self::validatePlugin('Keys', $format, 'savePublicKey');
        $key = $type::savePublicKey($this->curve, $this->QA);
        $key = \FluentSmtpLib\phpseclib3\Crypt\EC::loadFormat($format, $key);
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery) {
            return $key;
        }
        $key = $key->withHash($this->hash->getHash())->withSignatureFormat($this->shortFormat);
        if ($this->curve instanceof \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\TwistedEdwards) {
            $key = $key->withContext($this->context);
        }
        return $key;
    }
    /**
     * Returns a signature in the appropriate format
     *
     * @return string
     */
    private function formatSignature(\FluentSmtpLib\phpseclib3\Math\BigInteger $r, \FluentSmtpLib\phpseclib3\Math\BigInteger $s)
    {
        $format = $this->sigFormat;
        $temp = new \ReflectionMethod($format, 'save');
        $paramCount = $temp->getNumberOfRequiredParameters();
        // @codingStandardsIgnoreStart
        switch ($paramCount) {
            case 2:
                return $format::save($r, $s);
            case 3:
                return $format::save($r, $s, $this->getCurve());
            case 4:
                return $format::save($r, $s, $this->getCurve(), $this->getLength());
        }
        // @codingStandardsIgnoreEnd
        // presumably the only way you could get to this is if you were using a custom plugin
        throw new \FluentSmtpLib\phpseclib3\Exception\UnsupportedOperationException("{$format}::save() has {$paramCount} parameters - the only valid parameter counts are 2 or 3");
    }
}
