<?php

declare(strict_types=1);

namespace PbClasses\Crypt;

use InvalidArgumentException;
use RuntimeException;

/**
 * Encryption and decryption utility using OpenSSL.
 * 
 * When encrypting text, a key-IV pair can be automatically generated.
 * These values can then be retrieved in readable (hex) format using getKey() and getIv().
 * 
 * For decryption, pass the key and IV in hex format to the constructor.
 * 
 * @author Peter Bieling - optimized by claude.ai - 2026-01-15
 */
class Crypt
{
    private const DEFAULT_CIPHER = 'aes-256-cbc';
    
    private string $cipher;
    private string $key;
    private string $iv;
    
    /**
     * Create a new Crypt instance.
     * 
     * @param string|null $key Encryption key in hex format (null to auto-generate)
     * @param string|null $iv Initialization vector in hex format (null to auto-generate)
     * 
     * @throws InvalidArgumentException If parameters are invalid
     * @throws RuntimeException If cipher is not supported or random bytes generation fails
     */
    public function __construct(?string $key = null, ?string $iv = null)
    {
        $this->cipher = self::DEFAULT_CIPHER;
        
        if ($key === null && $iv === null) {
            $this->generateKeyAndIv();
        } elseif ($key !== null && $iv !== null) {
            $this->setKeyAndIv($key, $iv);
        } else {
            throw new InvalidArgumentException(
                'Both key and IV must be provided together, or both must be null for auto-generation'
            );
        }
    }
    
    /**
     * Set the cipher algorithm.
     * 
     * @param string $cipher The cipher algorithm (e.g., 'aes-256-cbc', 'aes-128-gcm')
     * 
     * @throws InvalidArgumentException If cipher is not supported
     */
    public function setCipher(string $cipher): void
    {
        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw new InvalidArgumentException("Unsupported cipher: {$cipher}");
        }
        
        $this->cipher = $cipher;
    }
    
    /**
     * Get the current cipher algorithm.
     * 
     * @return string
     */
    public function getCipher(): string
    {
        return $this->cipher;
    }
    
    /**
     * Get the encryption key in hex format.
     * 
     * @return string
     */
    public function getKey(): string
    {
        return bin2hex($this->key);
    }
    
    /**
     * Get the initialization vector in hex format.
     * 
     * @return string
     */
    public function getIv(): string
    {
        return bin2hex($this->iv);
    }
    
    /**
     * Encrypt the given text.
     * 
     * @param string $text The plaintext to encrypt
     * 
     * @return string The encrypted text (base64 encoded)
     * 
     * @throws RuntimeException If encryption fails
     */
    public function encrypt(string $text): string
    {
        $encrypted = openssl_encrypt($text, $this->cipher, $this->key, 0, $this->iv);
        
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        
        return $encrypted;
    }
    
    /**
     * Decrypt the given encrypted text.
     * 
     * @param string $encryptedText The encrypted text (base64 encoded)
     * 
     * @return string The decrypted plaintext
     * 
     * @throws RuntimeException If decryption fails
     */
    public function decrypt(string $encryptedText): string
    {
        $decrypted = openssl_decrypt($encryptedText, $this->cipher, $this->key, 0, $this->iv);
        
        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: ' . openssl_error_string());
        }
        
        return $decrypted;
    }
    
    /**
     * Generate a new random key and IV.
     * 
     * @throws RuntimeException If cipher is not supported or random bytes generation fails
     */
    private function generateKeyAndIv(): void
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        if ($ivLength === false) {
            throw new RuntimeException("Unable to determine IV length for cipher: {$this->cipher}");
        }
        
        try {
            $this->key = random_bytes(32);
            $this->iv = random_bytes($ivLength);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate random bytes: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Set key and IV from hex-encoded strings.
     * 
     * @param string $keyHex The encryption key in hex format
     * @param string $ivHex The initialization vector in hex format
     * 
     * @throws InvalidArgumentException If hex strings are invalid
     */
    private function setKeyAndIv(string $keyHex, string $ivHex): void
    {
        $key = @hex2bin($keyHex);
        $iv = @hex2bin($ivHex);
        
        if ($key === false) {
            throw new InvalidArgumentException('Invalid hex string provided for key');
        }
        
        if ($iv === false) {
            throw new InvalidArgumentException('Invalid hex string provided for IV');
        }
        
        $expectedIvLength = openssl_cipher_iv_length($this->cipher);
        
        if ($expectedIvLength === false) {
            throw new RuntimeException("Unable to determine IV length for cipher: {$this->cipher}");
        }
        
        if (strlen($iv) !== $expectedIvLength) {
            throw new InvalidArgumentException(
                "IV length mismatch: expected {$expectedIvLength} bytes, got " . strlen($iv)
            );
        }
        
        $this->key = $key;
        $this->iv = $iv;
    }
}