<?php

/**
 * secp256k1
 *
 * This is the curve used in Bitcoin
 *
 * PHP version 5 and 7
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2017 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC\Curves;

//use phpseclib3\Crypt\EC\BaseCurves\Prime;
use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\KoblitzPrime;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
//class secp256k1 extends Prime
class secp256k1 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\KoblitzPrime
{
    public function __construct()
    {
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('0000000000000000000000000000000000000000000000000000000000000000', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('0000000000000000000000000000000000000000000000000000000000000007', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16));
        $this->basis = [];
        $this->basis[] = ['a' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('3086D221A7D46BCDE86C90E49284EB15', -16), 'b' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('FF1BBC8129FEF177D790AB8056F5401B3D', -16)];
        $this->basis[] = ['a' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('114CA50F7A8E2F3F657C1108D9D44CFD8', -16), 'b' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('3086D221A7D46BCDE86C90E49284EB15', -16)];
        $this->beta = $this->factory->newInteger(new \FluentSmtpLib\phpseclib3\Math\BigInteger('7AE96A2B657C07106E64479EAC3434E99CF0497512F58995C1396C28719501EE', -16));
    }
}
