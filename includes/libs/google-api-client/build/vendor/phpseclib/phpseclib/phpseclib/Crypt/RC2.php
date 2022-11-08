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
 *    $rc2 = new \phpseclib\Crypt\RC2();
 *
 *    $rc2->setKey('abcdefgh');
 *
 *    $plaintext = str_repeat('a', 1024);
 *
 *    echo $rc2->decrypt($rc2->encrypt($plaintext));
 * ?>
 * </code>
 *
 * @category Crypt
 * @package  RC2
 * @author   Patrick Monnerat <pm@datasphere.ch>
 * @license  http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link     http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib\Crypt;

/**
 * Pure-PHP implementation of RC2.
 *
 * @package RC2
 * @access  public
 */
class RC2 extends Base
{
    /**
     * Block Length of the cipher
     *
     * @see \phpseclib\Crypt\Base::block_size
     * @var int
     * @access private
     */
    var $block_size = 8;
    /**
     * The Key
     *
     * @see \phpseclib\Crypt\Base::key
     * @see self::setKey()
     * @var string
     * @access private
     */
    var $key;
    /**
     * The Original (unpadded) Key
     *
     * @see \phpseclib\Crypt\Base::key
     * @see self::setKey()
     * @see self::encrypt()
     * @see self::decrypt()
     * @var string
     * @access private
     */
    var $orig_key = '';
    /**
     * Don't truncate / null pad key
     *
     * @see \phpseclib\Crypt\Base::_clearBuffers()
     * @var bool
     * @access private
     */
    var $skip_key_adjustment = \true;
    /**
     * Key Length (in bytes)
     *
     * @see \phpseclib\Crypt\RC2::setKeyLength()
     * @var int
     * @access private
     */
    var $key_length = 16;
    // = 128 bits
    /**
     * The mcrypt specific name of the cipher
     *
     * @see \phpseclib\Crypt\Base::cipher_name_mcrypt
     * @var string
     * @access private
     */
    var $cipher_name_mcrypt = 'rc2';
    /**
     * Optimizing value while CFB-encrypting
     *
     * @see \phpseclib\Crypt\Base::cfb_init_len
     * @var int
     * @access private
     */
    var $cfb_init_len = 500;
    /**
     * The key length in bits.
     *
     * @see self::setKeyLength()
     * @see self::setKey()
     * @var int
     * @access private
     * @internal Should be in range [1..1024].
     * @internal Changing this value after setting the key has no effect.
     */
    var $default_key_length = 1024;
    /**
     * The key length in bits.
     *
     * @see self::isValidEnine()
     * @see self::setKey()
     * @var int
     * @access private
     * @internal Should be in range [1..1024].
     */
    var $current_key_length;
    /**
     * The Key Schedule
     *
     * @see self::_setupKey()
     * @var array
     * @access private
     */
    var $keys;
    /**
     * Key expansion randomization table.
     * Twice the same 256-value sequence to save a modulus in key expansion.
     *
     * @see self::setKey()
     * @var array
     * @access private
     */
    var $pitable = array(0xd9, 0x78, 0xf9, 0xc4, 0x19, 0xdd, 0xb5, 0xed, 0x28, 0xe9, 0xfd, 0x79, 0x4a, 0xa0, 0xd8, 0x9d, 0xc6, 0x7e, 0x37, 0x83, 0x2b, 0x76, 0x53, 0x8e, 0x62, 0x4c, 0x64, 0x88, 0x44, 0x8b, 0xfb, 0xa2, 0x17, 0x9a, 0x59, 0xf5, 0x87, 0xb3, 0x4f, 0x13, 0x61, 0x45, 0x6d, 0x8d, 0x9, 0x81, 0x7d, 0x32, 0xbd, 0x8f, 0x40, 0xeb, 0x86, 0xb7, 0x7b, 0xb, 0xf0, 0x95, 0x21, 0x22, 0x5c, 0x6b, 0x4e, 0x82, 0x54, 0xd6, 0x65, 0x93, 0xce, 0x60, 0xb2, 0x1c, 0x73, 0x56, 0xc0, 0x14, 0xa7, 0x8c, 0xf1, 0xdc, 0x12, 0x75, 0xca, 0x1f, 0x3b, 0xbe, 0xe4, 0xd1, 0x42, 0x3d, 0xd4, 0x30, 0xa3, 0x3c, 0xb6, 0x26, 0x6f, 0xbf, 0xe, 0xda, 0x46, 0x69, 0x7, 0x57, 0x27, 0xf2, 0x1d, 0x9b, 0xbc, 0x94, 0x43, 0x3, 0xf8, 0x11, 0xc7, 0xf6, 0x90, 0xef, 0x3e, 0xe7, 0x6, 0xc3, 0xd5, 0x2f, 0xc8, 0x66, 0x1e, 0xd7, 0x8, 0xe8, 0xea, 0xde, 0x80, 0x52, 0xee, 0xf7, 0x84, 0xaa, 0x72, 0xac, 0x35, 0x4d, 0x6a, 0x2a, 0x96, 0x1a, 0xd2, 0x71, 0x5a, 0x15, 0x49, 0x74, 0x4b, 0x9f, 0xd0, 0x5e, 0x4, 0x18, 0xa4, 0xec, 0xc2, 0xe0, 0x41, 0x6e, 0xf, 0x51, 0xcb, 0xcc, 0x24, 0x91, 0xaf, 0x50, 0xa1, 0xf4, 0x70, 0x39, 0x99, 0x7c, 0x3a, 0x85, 0x23, 0xb8, 0xb4, 0x7a, 0xfc, 0x2, 0x36, 0x5b, 0x25, 0x55, 0x97, 0x31, 0x2d, 0x5d, 0xfa, 0x98, 0xe3, 0x8a, 0x92, 0xae, 0x5, 0xdf, 0x29, 0x10, 0x67, 0x6c, 0xba, 0xc9, 0xd3, 0x0, 0xe6, 0xcf, 0xe1, 0x9e, 0xa8, 0x2c, 0x63, 0x16, 0x1, 0x3f, 0x58, 0xe2, 0x89, 0xa9, 0xd, 0x38, 0x34, 0x1b, 0xab, 0x33, 0xff, 0xb0, 0xbb, 0x48, 0xc, 0x5f, 0xb9, 0xb1, 0xcd, 0x2e, 0xc5, 0xf3, 0xdb, 0x47, 0xe5, 0xa5, 0x9c, 0x77, 0xa, 0xa6, 0x20, 0x68, 0xfe, 0x7f, 0xc1, 0xad, 0xd9, 0x78, 0xf9, 0xc4, 0x19, 0xdd, 0xb5, 0xed, 0x28, 0xe9, 0xfd, 0x79, 0x4a, 0xa0, 0xd8, 0x9d, 0xc6, 0x7e, 0x37, 0x83, 0x2b, 0x76, 0x53, 0x8e, 0x62, 0x4c, 0x64, 0x88, 0x44, 0x8b, 0xfb, 0xa2, 0x17, 0x9a, 0x59, 0xf5, 0x87, 0xb3, 0x4f, 0x13, 0x61, 0x45, 0x6d, 0x8d, 0x9, 0x81, 0x7d, 0x32, 0xbd, 0x8f, 0x40, 0xeb, 0x86, 0xb7, 0x7b, 0xb, 0xf0, 0x95, 0x21, 0x22, 0x5c, 0x6b, 0x4e, 0x82, 0x54, 0xd6, 0x65, 0x93, 0xce, 0x60, 0xb2, 0x1c, 0x73, 0x56, 0xc0, 0x14, 0xa7, 0x8c, 0xf1, 0xdc, 0x12, 0x75, 0xca, 0x1f, 0x3b, 0xbe, 0xe4, 0xd1, 0x42, 0x3d, 0xd4, 0x30, 0xa3, 0x3c, 0xb6, 0x26, 0x6f, 0xbf, 0xe, 0xda, 0x46, 0x69, 0x7, 0x57, 0x27, 0xf2, 0x1d, 0x9b, 0xbc, 0x94, 0x43, 0x3, 0xf8, 0x11, 0xc7, 0xf6, 0x90, 0xef, 0x3e, 0xe7, 0x6, 0xc3, 0xd5, 0x2f, 0xc8, 0x66, 0x1e, 0xd7, 0x8, 0xe8, 0xea, 0xde, 0x80, 0x52, 0xee, 0xf7, 0x84, 0xaa, 0x72, 0xac, 0x35, 0x4d, 0x6a, 0x2a, 0x96, 0x1a, 0xd2, 0x71, 0x5a, 0x15, 0x49, 0x74, 0x4b, 0x9f, 0xd0, 0x5e, 0x4, 0x18, 0xa4, 0xec, 0xc2, 0xe0, 0x41, 0x6e, 0xf, 0x51, 0xcb, 0xcc, 0x24, 0x91, 0xaf, 0x50, 0xa1, 0xf4, 0x70, 0x39, 0x99, 0x7c, 0x3a, 0x85, 0x23, 0xb8, 0xb4, 0x7a, 0xfc, 0x2, 0x36, 0x5b, 0x25, 0x55, 0x97, 0x31, 0x2d, 0x5d, 0xfa, 0x98, 0xe3, 0x8a, 0x92, 0xae, 0x5, 0xdf, 0x29, 0x10, 0x67, 0x6c, 0xba, 0xc9, 0xd3, 0x0, 0xe6, 0xcf, 0xe1, 0x9e, 0xa8, 0x2c, 0x63, 0x16, 0x1, 0x3f, 0x58, 0xe2, 0x89, 0xa9, 0xd, 0x38, 0x34, 0x1b, 0xab, 0x33, 0xff, 0xb0, 0xbb, 0x48, 0xc, 0x5f, 0xb9, 0xb1, 0xcd, 0x2e, 0xc5, 0xf3, 0xdb, 0x47, 0xe5, 0xa5, 0x9c, 0x77, 0xa, 0xa6, 0x20, 0x68, 0xfe, 0x7f, 0xc1, 0xad);
    /**
     * Inverse key expansion randomization table.
     *
     * @see self::setKey()
     * @var array
     * @access private
     */
    var $invpitable = array(0xd1, 0xda, 0xb9, 0x6f, 0x9c, 0xc8, 0x78, 0x66, 0x80, 0x2c, 0xf8, 0x37, 0xea, 0xe0, 0x62, 0xa4, 0xcb, 0x71, 0x50, 0x27, 0x4b, 0x95, 0xd9, 0x20, 0x9d, 0x4, 0x91, 0xe3, 0x47, 0x6a, 0x7e, 0x53, 0xfa, 0x3a, 0x3b, 0xb4, 0xa8, 0xbc, 0x5f, 0x68, 0x8, 0xca, 0x8f, 0x14, 0xd7, 0xc0, 0xef, 0x7b, 0x5b, 0xbf, 0x2f, 0xe5, 0xe2, 0x8c, 0xba, 0x12, 0xe1, 0xaf, 0xb2, 0x54, 0x5d, 0x59, 0x76, 0xdb, 0x32, 0xa2, 0x58, 0x6e, 0x1c, 0x29, 0x64, 0xf3, 0xe9, 0x96, 0xc, 0x98, 0x19, 0x8d, 0x3e, 0x26, 0xab, 0xa5, 0x85, 0x16, 0x40, 0xbd, 0x49, 0x67, 0xdc, 0x22, 0x94, 0xbb, 0x3c, 0xc1, 0x9b, 0xeb, 0x45, 0x28, 0x18, 0xd8, 0x1a, 0x42, 0x7d, 0xcc, 0xfb, 0x65, 0x8e, 0x3d, 0xcd, 0x2a, 0xa3, 0x60, 0xae, 0x93, 0x8a, 0x48, 0x97, 0x51, 0x15, 0xf7, 0x1, 0xb, 0xb7, 0x36, 0xb1, 0x2e, 0x11, 0xfd, 0x84, 0x2d, 0x3f, 0x13, 0x88, 0xb3, 0x34, 0x24, 0x1b, 0xde, 0xc5, 0x1d, 0x4d, 0x2b, 0x17, 0x31, 0x74, 0xa9, 0xc6, 0x43, 0x6d, 0x39, 0x90, 0xbe, 0xc3, 0xb0, 0x21, 0x6b, 0xf6, 0xf, 0xd5, 0x99, 0xd, 0xac, 0x1f, 0x5c, 0x9e, 0xf5, 0xf9, 0x4c, 0xd6, 0xdf, 0x89, 0xe4, 0x8b, 0xff, 0xc7, 0xaa, 0xe7, 0xed, 0x46, 0x25, 0xb6, 0x6, 0x5e, 0x35, 0xb5, 0xec, 0xce, 0xe8, 0x6c, 0x30, 0x55, 0x61, 0x4a, 0xfe, 0xa0, 0x79, 0x3, 0xf0, 0x10, 0x72, 0x7c, 0xcf, 0x52, 0xa6, 0xa7, 0xee, 0x44, 0xd3, 0x9a, 0x57, 0x92, 0xd0, 0x5a, 0x7a, 0x41, 0x7f, 0xe, 0x0, 0x63, 0xf2, 0x4f, 0x5, 0x83, 0xc9, 0xa1, 0xd4, 0xdd, 0xc4, 0x56, 0xf4, 0xd2, 0x77, 0x81, 0x9, 0x82, 0x33, 0x9f, 0x7, 0x86, 0x75, 0x38, 0x4e, 0x69, 0xf1, 0xad, 0x23, 0x73, 0x87, 0x70, 0x2, 0xc2, 0x1e, 0xb8, 0xa, 0xfc, 0xe6);
    /**
     * Test for engine validity
     *
     * This is mainly just a wrapper to set things up for \phpseclib\Crypt\Base::isValidEngine()
     *
     * @see \phpseclib\Crypt\Base::__construct()
     * @param int $engine
     * @access public
     * @return bool
     */
    function isValidEngine($engine)
    {
        switch ($engine) {
            case self::ENGINE_OPENSSL:
                if ($this->current_key_length != 128 || \strlen($this->orig_key) < 16) {
                    return \false;
                }
                $this->cipher_name_openssl_ecb = 'rc2-ecb';
                $this->cipher_name_openssl = 'rc2-' . $this->_openssl_translate_mode();
        }
        return parent::isValidEngine($engine);
    }
    /**
     * Sets the key length.
     *
     * Valid key lengths are 8 to 1024.
     * Calling this function after setting the key has no effect until the next
     *  \phpseclib\Crypt\RC2::setKey() call.
     *
     * @access public
     * @param int $length in bits
     */
    function setKeyLength($length)
    {
        if ($length < 8) {
            $this->default_key_length = 1;
        } elseif ($length > 1024) {
            $this->default_key_length = 128;
        } else {
            $this->default_key_length = $length;
        }
        $this->current_key_length = $this->default_key_length;
        parent::setKeyLength($length);
    }
    /**
     * Returns the current key length
     *
     * @access public
     * @return int
     */
    function getKeyLength()
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
     * If the key is not explicitly set, it'll be assumed to be a single
     * null byte.
     *
     * @see \phpseclib\Crypt\Base::setKey()
     * @access public
     * @param string $key
     * @param int $t1 optional Effective key length in bits.
     */
    function setKey($key, $t1 = 0)
    {
        $this->orig_key = $key;
        if ($t1 <= 0) {
            $t1 = $this->default_key_length;
        } elseif ($t1 > 1024) {
            $t1 = 1024;
        }
        $this->current_key_length = $t1;
        // Key byte count should be 1..128.
        $key = \strlen($key) ? \substr($key, 0, 128) : "\x00";
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
        $pitable = $this->pitable;
        for ($i = $t; $i < 128; $i++) {
            $l[$i] = $pitable[$l[$i - 1] + $l[$i - $t]];
        }
        $i = 128 - $t8;
        $l[$i] = $pitable[$l[$i] & $tm];
        while ($i--) {
            $l[$i] = $pitable[$l[$i + 1] ^ $l[$i + $t8]];
        }
        // Prepare the key for mcrypt.
        $l[0] = $this->invpitable[$l[0]];
        \array_unshift($l, 'C*');
        parent::setKey(\call_user_func_array('pack', $l));
    }
    /**
     * Encrypts a message.
     *
     * Mostly a wrapper for \phpseclib\Crypt\Base::encrypt, with some additional OpenSSL handling code
     *
     * @see self::decrypt()
     * @access public
     * @param string $plaintext
     * @return string $ciphertext
     */
    function encrypt($plaintext)
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
     * Mostly a wrapper for \phpseclib\Crypt\Base::decrypt, with some additional OpenSSL handling code
     *
     * @see self::encrypt()
     * @access public
     * @param string $ciphertext
     * @return string $plaintext
     */
    function decrypt($ciphertext)
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
     * @see \phpseclib\Crypt\Base::_encryptBlock()
     * @see \phpseclib\Crypt\Base::encrypt()
     * @access private
     * @param string $in
     * @return string
     */
    function _encryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = \array_values(\unpack('v*', $in));
        $keys = $this->keys;
        $limit = 20;
        $actions = array($limit => 44, 44 => 64);
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
     * @see \phpseclib\Crypt\Base::_decryptBlock()
     * @see \phpseclib\Crypt\Base::decrypt()
     * @access private
     * @param string $in
     * @return string
     */
    function _decryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = \array_values(\unpack('v*', $in));
        $keys = $this->keys;
        $limit = 44;
        $actions = array($limit => 20, 20 => 0);
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
     * Setup the \phpseclib\Crypt\Base::ENGINE_MCRYPT $engine
     *
     * @see \phpseclib\Crypt\Base::_setupMcrypt()
     * @access private
     */
    function _setupMcrypt()
    {
        if (!isset($this->key)) {
            $this->setKey('');
        }
        parent::_setupMcrypt();
    }
    /**
     * Creates the key schedule
     *
     * @see \phpseclib\Crypt\Base::_setupKey()
     * @access private
     */
    function _setupKey()
    {
        if (!isset($this->key)) {
            $this->setKey('');
        }
        // Key has already been expanded in \phpseclib\Crypt\RC2::setKey():
        // Only the first value must be altered.
        $l = \unpack('Ca/Cb/v*', $this->key);
        \array_unshift($l, $this->pitable[$l['a']] | $l['b'] << 8);
        unset($l['a']);
        unset($l['b']);
        $this->keys = $l;
    }
    /**
     * Setup the performance-optimized function for de/encrypt()
     *
     * @see \phpseclib\Crypt\Base::_setupInlineCrypt()
     * @access private
     */
    function _setupInlineCrypt()
    {
        $lambda_functions =& self::_getLambdaFunctions();
        // The first 10 generated $lambda_functions will use the $keys hardcoded as integers
        // for the mixing rounds, for better inline crypt performance [~20% faster].
        // But for memory reason we have to limit those ultra-optimized $lambda_functions to an amount of 10.
        // (Currently, for Crypt_RC2, one generated $lambda_function cost on php5.5@32bit ~60kb unfreeable mem and ~100kb on php5.5@64bit)
        $gen_hi_opt_code = (bool) (\count($lambda_functions) < 10);
        // Generation of a unique hash for our generated code
        $code_hash = "Crypt_RC2, {$this->mode}";
        if ($gen_hi_opt_code) {
            $code_hash = \str_pad($code_hash, 32) . $this->_hashInlineCryptFunction($this->key);
        }
        // Is there a re-usable $lambda_functions in there?
        // If not, we have to create it.
        if (!isset($lambda_functions[$code_hash])) {
            // Init code for both, encrypt and decrypt.
            $init_crypt = '$keys = $self->keys;';
            switch (\true) {
                case $gen_hi_opt_code:
                    $keys = $this->keys;
                default:
                    $keys = array();
                    foreach ($this->keys as $k => $v) {
                        $keys[$k] = '$keys[' . $k . ']';
                    }
            }
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
            $actions = array($limit => 44, 44 => 64);
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
            $actions = array($limit => 20, 20 => 0);
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
            $lambda_functions[$code_hash] = $this->_createInlineCryptFunction(array('init_crypt' => $init_crypt, 'encrypt_block' => $encrypt_block, 'decrypt_block' => $decrypt_block));
        }
        // Set the inline-crypt function as callback in: $this->inline_crypt
        $this->inline_crypt = $lambda_functions[$code_hash];
    }
}
