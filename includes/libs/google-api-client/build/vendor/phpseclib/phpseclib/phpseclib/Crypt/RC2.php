<?php

/**
 * Pure-PHP implementation of RC2.
 *
 * Uses mcrypt, if available, and an internal implementation, otherwise.
 *
 * PHP version 5
 *
 * Useful resources are as follows:
 *
 *  - {@link http://tools.ietf.org/html/rfc2268}
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include 'vendor/autoload.php';
 *
 *    $rc2 = new \phpseclib3\Crypt\RC2('ctr');
 *
 *    $rc2->setKey('abcdefgh');
 *
 *    $plaintext = str_repeat('a', 1024);
 *
 *    echo $rc2->decrypt($rc2->encrypt($plaintext));
 * ?>
 * </code>
 *
 * @author   Patrick Monnerat <pm@datasphere.ch>
 * @license  http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link     http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt;

use FluentSmtpLib\phpseclib3\Crypt\Common\BlockCipher;
use FluentSmtpLib\phpseclib3\Exception\BadModeException;
/**
 * Pure-PHP implementation of RC2.
 *
 */
class RC2 extends \FluentSmtpLib\phpseclib3\Crypt\Common\BlockCipher
{
    /**
     * Block Length of the cipher
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::block_size
     * @var int
     */
    protected $block_size = 8;
    /**
     * The Key
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::key
     * @see self::setKey()
     * @var string
     */
    protected $key;
    /**
     * The Original (unpadded) Key
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::key
     * @see self::setKey()
     * @see self::encrypt()
     * @see self::decrypt()
     * @var string
     */
    private $orig_key;
    /**
     * Key Length (in bytes)
     *
     * @see \phpseclib3\Crypt\RC2::setKeyLength()
     * @var int
     */
    protected $key_length = 16;
    // = 128 bits
    /**
     * The mcrypt specific name of the cipher
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::cipher_name_mcrypt
     * @var string
     */
    protected $cipher_name_mcrypt = 'rc2';
    /**
     * Optimizing value while CFB-encrypting
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::cfb_init_len
     * @var int
     */
    protected $cfb_init_len = 500;
    /**
     * The key length in bits.
     *
     * {@internal Should be in range [1..1024].}
     *
     * {@internal Changing this value after setting the key has no effect.}
     *
     * @see self::setKeyLength()
     * @see self::setKey()
     * @var int
     */
    private $default_key_length = 1024;
    /**
     * The key length in bits.
     *
     * {@internal Should be in range [1..1024].}
     *
     * @see self::isValidEnine()
     * @see self::setKey()
     * @var int
     */
    private $current_key_length;
    /**
     * The Key Schedule
     *
     * @see self::setupKey()
     * @var array
     */
    private $keys;
    /**
     * Key expansion randomization table.
     * Twice the same 256-value sequence to save a modulus in key expansion.
     *
     * @see self::setKey()
     * @var array
     */
    private static $pitable = [0xd9, 0x78, 0xf9, 0xc4, 0x19, 0xdd, 0xb5, 0xed, 0x28, 0xe9, 0xfd, 0x79, 0x4a, 0xa0, 0xd8, 0x9d, 0xc6, 0x7e, 0x37, 0x83, 0x2b, 0x76, 0x53, 0x8e, 0x62, 0x4c, 0x64, 0x88, 0x44, 0x8b, 0xfb, 0xa2, 0x17, 0x9a, 0x59, 0xf5, 0x87, 0xb3, 0x4f, 0x13, 0x61, 0x45, 0x6d, 0x8d, 0x9, 0x81, 0x7d, 0x32, 0xbd, 0x8f, 0x40, 0xeb, 0x86, 0xb7, 0x7b, 0xb, 0xf0, 0x95, 0x21, 0x22, 0x5c, 0x6b, 0x4e, 0x82, 0x54, 0xd6, 0x65, 0x93, 0xce, 0x60, 0xb2, 0x1c, 0x73, 0x56, 0xc0, 0x14, 0xa7, 0x8c, 0xf1, 0xdc, 0x12, 0x75, 0xca, 0x1f, 0x3b, 0xbe, 0xe4, 0xd1, 0x42, 0x3d, 0xd4, 0x30, 0xa3, 0x3c, 0xb6, 0x26, 0x6f, 0xbf, 0xe, 0xda, 0x46, 0x69, 0x7, 0x57, 0x27, 0xf2, 0x1d, 0x9b, 0xbc, 0x94, 0x43, 0x3, 0xf8, 0x11, 0xc7, 0xf6, 0x90, 0xef, 0x3e, 0xe7, 0x6, 0xc3, 0xd5, 0x2f, 0xc8, 0x66, 0x1e, 0xd7, 0x8, 0xe8, 0xea, 0xde, 0x80, 0x52, 0xee, 0xf7, 0x84, 0xaa, 0x72, 0xac, 0x35, 0x4d, 0x6a, 0x2a, 0x96, 0x1a, 0xd2, 0x71, 0x5a, 0x15, 0x49, 0x74, 0x4b, 0x9f, 0xd0, 0x5e, 0x4, 0x18, 0xa4, 0xec, 0xc2, 0xe0, 0x41, 0x6e, 0xf, 0x51, 0xcb, 0xcc, 0x24, 0x91, 0xaf, 0x50, 0xa1, 0xf4, 0x70, 0x39, 0x99, 0x7c, 0x3a, 0x85, 0x23, 0xb8, 0xb4, 0x7a, 0xfc, 0x2, 0x36, 0x5b, 0x25, 0x55, 0x97, 0x31, 0x2d, 0x5d, 0xfa, 0x98, 0xe3, 0x8a, 0x92, 0xae, 0x5, 0xdf, 0x29, 0x10, 0x67, 0x6c, 0xba, 0xc9, 0xd3, 0x0, 0xe6, 0xcf, 0xe1, 0x9e, 0xa8, 0x2c, 0x63, 0x16, 0x1, 0x3f, 0x58, 0xe2, 0x89, 0xa9, 0xd, 0x38, 0x34, 0x1b, 0xab, 0x33, 0xff, 0xb0, 0xbb, 0x48, 0xc, 0x5f, 0xb9, 0xb1, 0xcd, 0x2e, 0xc5, 0xf3, 0xdb, 0x47, 0xe5, 0xa5, 0x9c, 0x77, 0xa, 0xa6, 0x20, 0x68, 0xfe, 0x7f, 0xc1, 0xad, 0xd9, 0x78, 0xf9, 0xc4, 0x19, 0xdd, 0xb5, 0xed, 0x28, 0xe9, 0xfd, 0x79, 0x4a, 0xa0, 0xd8, 0x9d, 0xc6, 0x7e, 0x37, 0x83, 0x2b, 0x76, 0x53, 0x8e, 0x62, 0x4c, 0x64, 0x88, 0x44, 0x8b, 0xfb, 0xa2, 0x17, 0x9a, 0x59, 0xf5, 0x87, 0xb3, 0x4f, 0x13, 0x61, 0x45, 0x6d, 0x8d, 0x9, 0x81, 0x7d, 0x32, 0xbd, 0x8f, 0x40, 0xeb, 0x86, 0xb7, 0x7b, 0xb, 0xf0, 0x95, 0x21, 0x22, 0x5c, 0x6b, 0x4e, 0x82, 0x54, 0xd6, 0x65, 0x93, 0xce, 0x60, 0xb2, 0x1c, 0x73, 0x56, 0xc0, 0x14, 0xa7, 0x8c, 0xf1, 0xdc, 0x12, 0x75, 0xca, 0x1f, 0x3b, 0xbe, 0xe4, 0xd1, 0x42, 0x3d, 0xd4, 0x30, 0xa3, 0x3c, 0xb6, 0x26, 0x6f, 0xbf, 0xe, 0xda, 0x46, 0x69, 0x7, 0x57, 0x27, 0xf2, 0x1d, 0x9b, 0xbc, 0x94, 0x43, 0x3, 0xf8, 0x11, 0xc7, 0xf6, 0x90, 0xef, 0x3e, 0xe7, 0x6, 0xc3, 0xd5, 0x2f, 0xc8, 0x66, 0x1e, 0xd7, 0x8, 0xe8, 0xea, 0xde, 0x80, 0x52, 0xee, 0xf7, 0x84, 0xaa, 0x72, 0xac, 0x35, 0x4d, 0x6a, 0x2a, 0x96, 0x1a, 0xd2, 0x71, 0x5a, 0x15, 0x49, 0x74, 0x4b, 0x9f, 0xd0, 0x5e, 0x4, 0x18, 0xa4, 0xec, 0xc2, 0xe0, 0x41, 0x6e, 0xf, 0x51, 0xcb, 0xcc, 0x24, 0x91, 0xaf, 0x50, 0xa1, 0xf4, 0x70, 0x39, 0x99, 0x7c, 0x3a, 0x85, 0x23, 0xb8, 0xb4, 0x7a, 0xfc, 0x2, 0x36, 0x5b, 0x25, 0x55, 0x97, 0x31, 0x2d, 0x5d, 0xfa, 0x98, 0xe3, 0x8a, 0x92, 0xae, 0x5, 0xdf, 0x29, 0x10, 0x67, 0x6c, 0xba, 0xc9, 0xd3, 0x0, 0xe6, 0xcf, 0xe1, 0x9e, 0xa8, 0x2c, 0x63, 0x16, 0x1, 0x3f, 0x58, 0xe2, 0x89, 0xa9, 0xd, 0x38, 0x34, 0x1b, 0xab, 0x33, 0xff, 0xb0, 0xbb, 0x48, 0xc, 0x5f, 0xb9, 0xb1, 0xcd, 0x2e, 0xc5, 0xf3, 0xdb, 0x47, 0xe5, 0xa5, 0x9c, 0x77, 0xa, 0xa6, 0x20, 0x68, 0xfe, 0x7f, 0xc1, 0xad];
    /**
     * Inverse key expansion randomization table.
     *
     * @see self::setKey()
     * @var array
     */
    private static $invpitable = [0xd1, 0xda, 0xb9, 0x6f, 0x9c, 0xc8, 0x78, 0x66, 0x80, 0x2c, 0xf8, 0x37, 0xea, 0xe0, 0x62, 0xa4, 0xcb, 0x71, 0x50, 0x27, 0x4b, 0x95, 0xd9, 0x20, 0x9d, 0x4, 0x91, 0xe3, 0x47, 0x6a, 0x7e, 0x53, 0xfa, 0x3a, 0x3b, 0xb4, 0xa8, 0xbc, 0x5f, 0x68, 0x8, 0xca, 0x8f, 0x14, 0xd7, 0xc0, 0xef, 0x7b, 0x5b, 0xbf, 0x2f, 0xe5, 0xe2, 0x8c, 0xba, 0x12, 0xe1, 0xaf, 0xb2, 0x54, 0x5d, 0x59, 0x76, 0xdb, 0x32, 0xa2, 0x58, 0x6e, 0x1c, 0x29, 0x64, 0xf3, 0xe9, 0x96, 0xc, 0x98, 0x19, 0x8d, 0x3e, 0x26, 0xab, 0xa5, 0x85, 0x16, 0x40, 0xbd, 0x49, 0x67, 0xdc, 0x22, 0x94, 0xbb, 0x3c, 0xc1, 0x9b, 0xeb, 0x45, 0x28, 0x18, 0xd8, 0x1a, 0x42, 0x7d, 0xcc, 0xfb, 0x65, 0x8e, 0x3d, 0xcd, 0x2a, 0xa3, 0x60, 0xae, 0x93, 0x8a, 0x48, 0x97, 0x51, 0x15, 0xf7, 0x1, 0xb, 0xb7, 0x36, 0xb1, 0x2e, 0x11, 0xfd, 0x84, 0x2d, 0x3f, 0x13, 0x88, 0xb3, 0x34, 0x24, 0x1b, 0xde, 0xc5, 0x1d, 0x4d, 0x2b, 0x17, 0x31, 0x74, 0xa9, 0xc6, 0x43, 0x6d, 0x39, 0x90, 0xbe, 0xc3, 0xb0, 0x21, 0x6b, 0xf6, 0xf, 0xd5, 0x99, 0xd, 0xac, 0x1f, 0x5c, 0x9e, 0xf5, 0xf9, 0x4c, 0xd6, 0xdf, 0x89, 0xe4, 0x8b, 0xff, 0xc7, 0xaa, 0xe7, 0xed, 0x46, 0x25, 0xb6, 0x6, 0x5e, 0x35, 0xb5, 0xec, 0xce, 0xe8, 0x6c, 0x30, 0x55, 0x61, 0x4a, 0xfe, 0xa0, 0x79, 0x3, 0xf0, 0x10, 0x72, 0x7c, 0xcf, 0x52, 0xa6, 0xa7, 0xee, 0x44, 0xd3, 0x9a, 0x57, 0x92, 0xd0, 0x5a, 0x7a, 0x41, 0x7f, 0xe, 0x0, 0x63, 0xf2, 0x4f, 0x5, 0x83, 0xc9, 0xa1, 0xd4, 0xdd, 0xc4, 0x56, 0xf4, 0xd2, 0x77, 0x81, 0x9, 0x82, 0x33, 0x9f, 0x7, 0x86, 0x75, 0x38, 0x4e, 0x69, 0xf1, 0xad, 0x23, 0x73, 0x87, 0x70, 0x2, 0xc2, 0x1e, 0xb8, 0xa, 0xfc, 0xe6];
    /**
     * Default Constructor.
     *
     * @param string $mode
     * @throws \InvalidArgumentException if an invalid / unsupported mode is provided
     */
    public function __construct($mode)
    {
        parent::__construct($mode);
        if ($this->mode == self::MODE_STREAM) {
            throw new \FluentSmtpLib\phpseclib3\Exception\BadModeException('Block ciphers cannot be ran in stream mode');
        }
    }
    /**
     * Test for engine validity
     *
     * This is mainly just a wrapper to set things up for \phpseclib3\Crypt\Common\SymmetricKey::isValidEngine()
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::__construct()
     * @param int $engine
     * @return bool
     */
    protected function isValidEngineHelper($engine)
    {
        switch ($engine) {
            case self::ENGINE_OPENSSL:
                if ($this->current_key_length != 128 || \strlen($this->orig_key) < 16) {
                    return \false;
                }
                // quoting https://www.openssl.org/news/openssl-3.0-notes.html, OpenSSL 3.0.1
                // "Moved all variations of the EVP ciphers CAST5, BF, IDEA, SEED, RC2, RC4, RC5, and DES to the legacy provider"
                // in theory openssl_get_cipher_methods() should catch this but, on GitHub Actions, at least, it does not
                if (\defined('OPENSSL_VERSION_TEXT') && \version_compare(\preg_replace('#OpenSSL (\\d+\\.\\d+\\.\\d+) .*#', '$1', \OPENSSL_VERSION_TEXT), '3.0.1', '>=')) {
                    return \false;
                }
                $this->cipher_name_openssl_ecb = 'rc2-ecb';
                $this->cipher_name_openssl = 'rc2-' . $this->openssl_translate_mode();
        }
        return parent::isValidEngineHelper($engine);
    }
    /**
     * Sets the key length.
     *
     * Valid key lengths are 8 to 1024.
     * Calling this function after setting the key has no effect until the next
     *  \phpseclib3\Crypt\RC2::setKey() call.
     *
     * @param int $length in bits
     * @throws \LengthException if the key length isn't supported
     */
    public function setKeyLength($length)
    {
        if ($length < 8 || $length > 1024) {
            throw new \LengthException('Key size of ' . $length . ' bits is not supported by this algorithm. Only keys between 1 and 1024 bits, inclusive, are supported');
        }
        $this->default_key_length = $this->current_key_length = $length;
        $this->explicit_key_length = $length >> 3;
    }
    /**
     * Returns the current key length
     *
     * @return int
     */
    public function getKeyLength()
    {
        return $this->current_key_length;
    }
    /**
     * Sets the key.
     *
     * Keys can be of any length. RC2, itself, uses 8 to 1024 bit keys (eg.
     * strlen($key) <= 128), however, we only use the first 128 bytes if $key
     * has more then 128 bytes in it, and set $key to a single null byte if
     * it is empty.
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::setKey()
     * @param string $key
     * @param int|boolean $t1 optional Effective key length in bits.
     * @throws \LengthException if the key length isn't supported
     */
    public function setKey($key, $t1 = \false)
    {
        $this->orig_key = $key;
        if ($t1 === \false) {
            $t1 = $this->default_key_length;
        }
        if ($t1 < 1 || $t1 > 1024) {
            throw new \LengthException('Key size of ' . $length . ' bits is not supported by this algorithm. Only keys between 1 and 1024 bits, inclusive, are supported');
        }
        $this->current_key_length = $t1;
        if (\strlen($key) < 1 || \strlen($key) > 128) {
            throw new \LengthException('Key of size ' . \strlen($key) . ' not supported by this algorithm. Only keys of sizes between 8 and 1024 bits, inclusive, are supported');
        }
        $t = \strlen($key);
        // The mcrypt RC2 implementation only supports effective key length
        // of 1024 bits. It is however possible to handle effective key
        // lengths in range 1..1024 by expanding the key and applying
        // inverse pitable mapping to the first byte before submitting it
        // to mcrypt.
        // Key expansion.
        $l = \array_values(\unpack('C*', $key));
        $t8 = $t1 + 7 >> 3;
        $tm = 0xff >> 8 * $t8 - $t1;
        // Expand key.
        $pitable = self::$pitable;
        for ($i = $t; $i < 128; $i++) {
            $l[$i] = $pitable[$l[$i - 1] + $l[$i - $t]];
        }
        $i = 128 - $t8;
        $l[$i] = $pitable[$l[$i] & $tm];
        while ($i--) {
            $l[$i] = $pitable[$l[$i + 1] ^ $l[$i + $t8]];
        }
        // Prepare the key for mcrypt.
        $l[0] = self::$invpitable[$l[0]];
        \array_unshift($l, 'C*');
        $this->key = \pack(...$l);
        $this->key_length = \strlen($this->key);
        $this->changed = $this->nonIVChanged = \true;
        $this->setEngine();
    }
    /**
     * Encrypts a message.
     *
     * Mostly a wrapper for \phpseclib3\Crypt\Common\SymmetricKey::encrypt, with some additional OpenSSL handling code
     *
     * @see self::decrypt()
     * @param string $plaintext
     * @return string $ciphertext
     */
    public function encrypt($plaintext)
    {
        if ($this->engine == self::ENGINE_OPENSSL) {
            $temp = $this->key;
            $this->key = $this->orig_key;
            $result = parent::encrypt($plaintext);
            $this->key = $temp;
            return $result;
        }
        return parent::encrypt($plaintext);
    }
    /**
     * Decrypts a message.
     *
     * Mostly a wrapper for \phpseclib3\Crypt\Common\SymmetricKey::decrypt, with some additional OpenSSL handling code
     *
     * @see self::encrypt()
     * @param string $ciphertext
     * @return string $plaintext
     */
    public function decrypt($ciphertext)
    {
        if ($this->engine == self::ENGINE_OPENSSL) {
            $temp = $this->key;
            $this->key = $this->orig_key;
            $result = parent::decrypt($ciphertext);
            $this->key = $temp;
            return $result;
        }
        return parent::decrypt($ciphertext);
    }
    /**
     * Encrypts a block
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::encryptBlock()
     * @see \phpseclib3\Crypt\Common\SymmetricKey::encrypt()
     * @param string $in
     * @return string
     */
    protected function encryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = \array_values(\unpack('v*', $in));
        $keys = $this->keys;
        $limit = 20;
        $actions = [$limit => 44, 44 => 64];
        $j = 0;
        for (;;) {
            // Mixing round.
            $r0 = ($r0 + $keys[$j++] + (($r1 ^ $r2) & $r3 ^ $r1) & 0xffff) << 1;
            $r0 |= $r0 >> 16;
            $r1 = ($r1 + $keys[$j++] + (($r2 ^ $r3) & $r0 ^ $r2) & 0xffff) << 2;
            $r1 |= $r1 >> 16;
            $r2 = ($r2 + $keys[$j++] + (($r3 ^ $r0) & $r1 ^ $r3) & 0xffff) << 3;
            $r2 |= $r2 >> 16;
            $r3 = ($r3 + $keys[$j++] + (($r0 ^ $r1) & $r2 ^ $r0) & 0xffff) << 5;
            $r3 |= $r3 >> 16;
            if ($j === $limit) {
                if ($limit === 64) {
                    break;
                }
                // Mashing round.
                $r0 += $keys[$r3 & 0x3f];
                $r1 += $keys[$r0 & 0x3f];
                $r2 += $keys[$r1 & 0x3f];
                $r3 += $keys[$r2 & 0x3f];
                $limit = $actions[$limit];
            }
        }
        return \pack('vvvv', $r0, $r1, $r2, $r3);
    }
    /**
     * Decrypts a block
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::decryptBlock()
     * @see \phpseclib3\Crypt\Common\SymmetricKey::decrypt()
     * @param string $in
     * @return string
     */
    protected function decryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = \array_values(\unpack('v*', $in));
        $keys = $this->keys;
        $limit = 44;
        $actions = [$limit => 20, 20 => 0];
        $j = 64;
        for (;;) {
            // R-mixing round.
            $r3 = ($r3 | $r3 << 16) >> 5;
            $r3 = $r3 - $keys[--$j] - (($r0 ^ $r1) & $r2 ^ $r0) & 0xffff;
            $r2 = ($r2 | $r2 << 16) >> 3;
            $r2 = $r2 - $keys[--$j] - (($r3 ^ $r0) & $r1 ^ $r3) & 0xffff;
            $r1 = ($r1 | $r1 << 16) >> 2;
            $r1 = $r1 - $keys[--$j] - (($r2 ^ $r3) & $r0 ^ $r2) & 0xffff;
            $r0 = ($r0 | $r0 << 16) >> 1;
            $r0 = $r0 - $keys[--$j] - (($r1 ^ $r2) & $r3 ^ $r1) & 0xffff;
            if ($j === $limit) {
                if ($limit === 0) {
                    break;
                }
                // R-mashing round.
                $r3 = $r3 - $keys[$r2 & 0x3f] & 0xffff;
                $r2 = $r2 - $keys[$r1 & 0x3f] & 0xffff;
                $r1 = $r1 - $keys[$r0 & 0x3f] & 0xffff;
                $r0 = $r0 - $keys[$r3 & 0x3f] & 0xffff;
                $limit = $actions[$limit];
            }
        }
        return \pack('vvvv', $r0, $r1, $r2, $r3);
    }
    /**
     * Creates the key schedule
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::setupKey()
     */
    protected function setupKey()
    {
        if (!isset($this->key)) {
            $this->setKey('');
        }
        // Key has already been expanded in \phpseclib3\Crypt\RC2::setKey():
        // Only the first value must be altered.
        $l = \unpack('Ca/Cb/v*', $this->key);
        \array_unshift($l, self::$pitable[$l['a']] | $l['b'] << 8);
        unset($l['a']);
        unset($l['b']);
        $this->keys = $l;
    }
    /**
     * Setup the performance-optimized function for de/encrypt()
     *
     * @see \phpseclib3\Crypt\Common\SymmetricKey::setupInlineCrypt()
     */
    protected function setupInlineCrypt()
    {
        // Init code for both, encrypt and decrypt.
        $init_crypt = '$keys = $this->keys;';
        $keys = $this->keys;
        // $in is the current 8 bytes block which has to be en/decrypt
        $encrypt_block = $decrypt_block = '
            $in = unpack("v4", $in);
            $r0 = $in[1];
            $r1 = $in[2];
            $r2 = $in[3];
            $r3 = $in[4];
        ';
        // Create code for encryption.
        $limit = 20;
        $actions = [$limit => 44, 44 => 64];
        $j = 0;
        for (;;) {
            // Mixing round.
            $encrypt_block .= '
                $r0 = (($r0 + ' . $keys[$j++] . ' +
                       ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF) << 1;
                $r0 |= $r0 >> 16;
                $r1 = (($r1 + ' . $keys[$j++] . ' +
                       ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF) << 2;
                $r1 |= $r1 >> 16;
                $r2 = (($r2 + ' . $keys[$j++] . ' +
                       ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF) << 3;
                $r2 |= $r2 >> 16;
                $r3 = (($r3 + ' . $keys[$j++] . ' +
                       ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF) << 5;
                $r3 |= $r3 >> 16;';
            if ($j === $limit) {
                if ($limit === 64) {
                    break;
                }
                // Mashing round.
                $encrypt_block .= '
                    $r0 += $keys[$r3 & 0x3F];
                    $r1 += $keys[$r0 & 0x3F];
                    $r2 += $keys[$r1 & 0x3F];
                    $r3 += $keys[$r2 & 0x3F];';
                $limit = $actions[$limit];
            }
        }
        $encrypt_block .= '$in = pack("v4", $r0, $r1, $r2, $r3);';
        // Create code for decryption.
        $limit = 44;
        $actions = [$limit => 20, 20 => 0];
        $j = 64;
        for (;;) {
            // R-mixing round.
            $decrypt_block .= '
                $r3 = ($r3 | ($r3 << 16)) >> 5;
                $r3 = ($r3 - ' . $keys[--$j] . ' -
                       ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF;
                $r2 = ($r2 | ($r2 << 16)) >> 3;
                $r2 = ($r2 - ' . $keys[--$j] . ' -
                       ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF;
                $r1 = ($r1 | ($r1 << 16)) >> 2;
                $r1 = ($r1 - ' . $keys[--$j] . ' -
                       ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF;
                $r0 = ($r0 | ($r0 << 16)) >> 1;
                $r0 = ($r0 - ' . $keys[--$j] . ' -
                       ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF;';
            if ($j === $limit) {
                if ($limit === 0) {
                    break;
                }
                // R-mashing round.
                $decrypt_block .= '
                    $r3 = ($r3 - $keys[$r2 & 0x3F]) & 0xFFFF;
                    $r2 = ($r2 - $keys[$r1 & 0x3F]) & 0xFFFF;
                    $r1 = ($r1 - $keys[$r0 & 0x3F]) & 0xFFFF;
                    $r0 = ($r0 - $keys[$r3 & 0x3F]) & 0xFFFF;';
                $limit = $actions[$limit];
            }
        }
        $decrypt_block .= '$in = pack("v4", $r0, $r1, $r2, $r3);';
        // Creates the inline-crypt function
        $this->inline_crypt = $this->createInlineCryptFunction(['init_crypt' => $init_crypt, 'encrypt_block' => $encrypt_block, 'decrypt_block' => $decrypt_block]);
    }
}
