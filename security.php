<?php
require_once __DIR__ . '/config.php';

function sign_payload(string $payload): string {
    return hash_hmac('sha256', $payload, HMAC_KEY);
}

function verify_signature(string $payload, string $signature): bool {
    return hash_equals(sign_payload($payload), $signature);
}

function encrypt_payload(string $plainText): string {
    $iv  = random_bytes(12);
    $tag = '';

    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($cipherText === false || $tag === null || $tag === '') {
        throw new RuntimeException('Crypto Engine: Encryption failed');
    }

    return base64_encode($iv) . '.' . base64_encode($cipherText . $tag);
}

function decrypt_payload(string $encryptedPayload): string {
    $parts = explode('.', $encryptedPayload, 2);
    if (count($parts) !== 2) {
        throw new InvalidArgumentException('Crypto Engine: Encrypted payload split format invalid');
    }

    $iv       = base64_decode($parts[0], true);
    $combined = base64_decode($parts[1], true);

    if ($iv === false || $combined === false || strlen($combined) <= 16) {
        throw new InvalidArgumentException('Crypto Engine: Base64 decode failed or payload too short');
    }

    $tag        = substr($combined, -16);
    $cipherText = substr($combined, 0, -16);

    $plainText = openssl_decrypt(
        $cipherText,
        'aes-256-gcm',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plainText === false) {
        throw new RuntimeException('Crypto Engine: Decryption failed (Key mismatch)');
    }

    return $plainText;
}

function get_request_payload(): ?array {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) return null;

    $input = json_decode($rawInput, true);
    if (!$input) return null;

    if (isset($input['encrypted_payload'])) {
        $decryptedJson = decrypt_payload($input['encrypted_payload']);
        if (isset($input['signature'])) {
            if (!verify_signature($decryptedJson, $input['signature'])) {
                throw new RuntimeException('Signature verification failed');
            }
        }
        return json_decode($decryptedJson, true);
    }

    return $input;
}

function send_secure_response($data, int $status = 200) {
    http_response_code($status);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    $encryptedPayload = encrypt_payload($json);
    $signature        = sign_payload($json);

    header("Content-Type: application/json; charset=utf-8");
    header("X-Signature: " . $signature);

    echo json_encode([
        'encrypted_payload' => $encryptedPayload,
        'signature'         => $signature
    ]);
    exit();
}

function verify_hmac($payload, $receivedSignature) {
    return verify_signature($payload, $receivedSignature);
}

function generate_hmac($payload) {
    return sign_payload($payload);
}

function encrypt_data($data) {
    $json = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return encrypt_payload($json);
}

function decrypt_data($base64Data) {
    $plain = decrypt_payload($base64Data);
    return json_decode($plain, true);
}

