<?php

/**
 * secp224k1
 *
 * PHP version 5 and 7
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2017 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC\Curves;

use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\KoblitzPrime;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
class secp224k1 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\KoblitzPrime
{
    public function __construct()
    {
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFE56D', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('00000000000000000000000000000000000000000000000000000000', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('00000000000000000000000000000000000000000000000000000005', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('A1455B334DF099DF30FC28A169A467E9E47075A90F7E650EB6B7A45C', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('7E089FED7FBA344282CAFBD6F7E319F7C0B0BD59E2CA4BDB556D61A5', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('010000000000000000000000000001DCE8D2EC6184CAF0A971769FB1F7', 16));
        $this->basis = [];
        $this->basis[] = ['a' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('00B8ADF1378A6EB73409FA6C9C637D', -16), 'b' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('94730F82B358A3776A826298FA6F', -16)];
        $this->basis[] = ['a' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('01DCE8D2EC6184CAF0A972769FCC8B', -16), 'b' => new \FluentSmtpLib\phpseclib3\Math\BigInteger('4D2100BA3DC75AAB747CCF355DEC', -16)];
        $this->beta = $this->factory->newInteger(new \FluentSmtpLib\phpseclib3\Math\BigInteger('01F178FFA4B17C89E6F73AECE2AAD57AF4C0A748B63C830947B27E04', -16));
    }
}
