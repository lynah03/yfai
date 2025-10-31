<?php
    declare(strict_types=1);

    namespace App\Security\Secrets;

    final class Crypto
    {
        private string $key; // 32-byte binary key

        public function __construct(string $key)
        {
            $this->key = $this->normalizeKey($key);
        }

        private function normalizeKey(string $raw): string
        {
            $k = trim($raw);

            // Support "base64:..." prefix
            if (str_starts_with($k, 'base64:')) {
                $k = substr($k, 7);
            }

            // Try base64
            $b64 = base64_decode($k, true);
            if ($b64 !== false && strlen($b64) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $b64;
            }

            // Try hex (64 hex chars)
            if (preg_match('/^[0-9a-fA-F]{64}$/', $k)) {
                $bin = hex2bin($k);
                if ($bin !== false && strlen($bin) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                    return $bin;
                }
            }

            // If someone passed raw 32-byte binary (rare via env), accept it
            if (strlen($k) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $k;
            }

            throw new \InvalidArgumentException(sprintf(
                'MAIL_CRYPT_KEY must be a 32-byte key (base64 or 64-char hex). Got %d bytes after decoding.',
                $b64 !== false ? strlen($b64) : strlen($k)
            ));
        }

        public function encrypt(string $plain): string
        {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ct    = sodium_crypto_secretbox($plain, $nonce, $this->key);
            return base64_encode($nonce . $ct); // store as text
        }

        public function decrypt(string $b64): string
        {
            $raw = base64_decode($b64, true);
            if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) {
                throw new \InvalidArgumentException('Encrypted payload is not valid base64 or too short.');
            }

            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ct    = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $pt = sodium_crypto_secretbox_open($ct, $nonce, $this->key);
            if ($pt === false) {
                throw new \RuntimeException('Decryption failed (wrong key or corrupted payload).');
            }
            return $pt;
        }
    }
