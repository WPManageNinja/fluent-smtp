<?php

/**
 * Prime Finite Fields
 *
 * Utilizes the factory design pattern
 *
 * PHP version 5 and 7
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2017 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 */
namespace FluentSmtpLib\phpseclib3\Math;

use FluentSmtpLib\phpseclib3\Math\Common\FiniteField;
use FluentSmtpLib\phpseclib3\Math\PrimeField\Integer;
/**
 * Prime Finite Fields
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
class PrimeField extends \FluentSmtpLib\phpseclib3\Math\Common\FiniteField
{
    /**
     * Instance Counter
     *
     * @var int
     */
    private static $instanceCounter = 0;
    /**
     * Keeps track of current instance
     *
     * @var int
     */
    protected $instanceID;
    /**
     * Default constructor
     */
    public function __construct(\FluentSmtpLib\phpseclib3\Math\BigInteger $modulo)
    {
        if (!$modulo->isPrime()) {
            throw new \UnexpectedValueException('PrimeField requires a prime number be passed to the constructor');
        }
        $this->instanceID = self::$instanceCounter++;
        \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::setModulo($this->instanceID, $modulo);
        \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::setRecurringModuloFunction($this->instanceID, $modulo->createRecurringModuloFunction());
    }
    /**
     * Use a custom defined modular reduction function
     *
     * @return void
     */
    public function setReduction(\Closure $func)
    {
        $this->reduce = $func->bindTo($this, $this);
    }
    /**
     * Returns an instance of a dynamically generated PrimeFieldInteger class
     *
     * @return Integer
     */
    public function newInteger(\FluentSmtpLib\phpseclib3\Math\BigInteger $num)
    {
        return new \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer($this->instanceID, $num);
    }
    /**
     * Returns an integer on the finite field between one and the prime modulo
     *
     * @return Integer
     */
    public function randomInteger()
    {
        static $one;
        if (!isset($one)) {
            $one = new \FluentSmtpLib\phpseclib3\Math\BigInteger(1);
        }
        return new \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer($this->instanceID, \FluentSmtpLib\phpseclib3\Math\BigInteger::randomRange($one, \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::getModulo($this->instanceID)));
    }
    /**
     * Returns the length of the modulo in bytes
     *
     * @return int
     */
    public function getLengthInBytes()
    {
        return \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::getModulo($this->instanceID)->getLengthInBytes();
    }
    /**
     * Returns the length of the modulo in bits
     *
     * @return int
     */
    public function getLength()
    {
        return \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::getModulo($this->instanceID)->getLength();
    }
    /**
     *  Destructor
     */
    public function __destruct()
    {
        \FluentSmtpLib\phpseclib3\Math\PrimeField\Integer::cleanupCache($this->instanceID);
    }
}
