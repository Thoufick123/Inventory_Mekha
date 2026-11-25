<?php
// app/services/CloudinaryService.php

namespace App\Services;

use App\Core\Env;
use App\Core\Controller;

class CloudinaryService {

    /**
     * Get Cloudinary credentials from environment (.env).
     * Supports both CLOUDINARY_URL format and individual CLOUDINARY_* keys.
     *
     * @return array|null [cloud_name, api_key, api_secret, upload_preset]
     */
    public static function getCredentials(): ?array {
        $cloudinaryUrl = Env::get('CLOUDINARY_URL');
        $cloudName = Env::get('CLOUDINARY_CLOUD_NAME');
        $apiKey    = Env::get('CLOUDINARY_API_KEY');
        $apiSecret = Env::get('CLOUDINARY_API_SECRET');
        $preset    = Env::get('CLOUDINARY_UPLOAD_PRESET');

        if (!empty($cloudinaryUrl) && str_starts_with($cloudinaryUrl, 'cloudinary://')) {
            $parsed = parse_url($cloudinaryUrl);
            if ($parsed && !empty($parsed['host'])) {
                $cloudName = $parsed['host'];
                $apiKey    = $parsed['user'] ?? $apiKey;
                $apiSecret = $parsed['pass'] ?? $apiSecret;
            }
        }

        if (!empty($cloudName) && (!empty($preset) || (!empty($apiKey) && !empty($apiSecret)))) {
            return [
                'cloud_name'    => trim((string)$cloudName),
                'api_key'       => trim((string)$apiKey),
                'api_secret'    => trim((string)$apiSecret),
                'upload_preset' => trim((string)$preset),
            ];
        }

        return null;
    }

    /**
     * Check if Cloudinary is configured with valid credentials.
     */
    public static function isConfigured(): bool {
        return self::getCredentials() !== null;
    }

    /**
     * Upload a local file directly to Cloudinary using cURL (pure PHP, zero dependencies).
     *
     * @param string $filePath Full path to the file on local disk
     * @param string $originalName Original filename for metadata
     * @param string $folder Destination folder in Cloudinary (e.g. 'invento/rolls')
     * @return array|null Returns Cloudinary response array with 'secure_url' on success, or null on failure
     */
    public static function uploadFile(string $filePath, string $originalName = '', string $folder = 'invento'): ?array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $creds = self::getCredentials();
        if (!$creds) {
            return null;
        }

        $cloudName    = $creds['cloud_name'];
        $apiKey       = $creds['api_key'];
        $apiSecret    = $creds['api_secret'];
        $uploadPreset = $creds['upload_preset'];

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload";

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $postFilename = $originalName ?: basename($filePath);
        $cFile = new \CURLFile($filePath, $mime, $postFilename);

        $postFields = [
            'file'   => $cFile,
            'folder' => $folder,
        ];

        // Signed upload if api_key and api_secret are provided
        if (!empty($apiKey) && !empty($apiSecret)) {
            $timestamp = time();
            $postFields['api_key']   = $apiKey;
            $postFields['timestamp'] = $timestamp;

            // Alphabetical order for signature
            $signParams = [
                'folder'    => $folder,
                'timestamp' => $timestamp,
            ];
            ksort($signParams);
            $signQuery = '';
            foreach ($signParams as $k => $v) {
                $signQuery .= ($signQuery === '' ? '' : '&') . "{$k}={$v}";
            }
            $signature = sha1($signQuery . $apiSecret);
            $postFields['signature'] = $signature;
        } elseif (!empty($uploadPreset)) {
            // Unsigned upload with upload preset
            $postFields['upload_preset'] = $uploadPreset;
        } else {
            return null;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode < 200 || $httpCode >= 300) {
            // Log warning or silently fail for graceful fallback
            error_log("Cloudinary Upload Error [HTTP {$httpCode}]: {$curlErr} | Response: {$response}");
            return null;
        }

        $data = json_decode($response, true);
        if (is_array($data) && !empty($data['secure_url'])) {
            return $data;
        }

        return null;
    }

    /**
     * Helper to normalize a nested $_FILES array like $_FILES['rolls']['name'][$idx]['photo']
     */
    public static function normalizeNestedFile(array $nestedFiles, $idx, string $field): ?array {
        if (
            empty($nestedFiles['name'][$idx][$field]) ||
            !isset($nestedFiles['error'][$idx][$field]) ||
            $nestedFiles['error'][$idx][$field] !== UPLOAD_ERR_OK ||
            empty($nestedFiles['tmp_name'][$idx][$field])
        ) {
            return null;
        }

        return [
            'name'     => (string)$nestedFiles['name'][$idx][$field],
            'tmp_name' => (string)$nestedFiles['tmp_name'][$idx][$field],
            'error'    => (int)$nestedFiles['error'][$idx][$field],
            'size'     => (int)($nestedFiles['size'][$idx][$field] ?? 0),
        ];
    }

    /**
     * Smart uploader:
     * 1. If Cloudinary is configured, uploads directly to Cloudinary and returns secure_url.
     * 2. If Cloudinary is unconfigured or upload fails, falls back gracefully to local disk storage
     *    and returns relative path (e.g. 'storage/uploads/{$subfolder}/...').
     *
     * @param array $file Single file array with 'tmp_name', 'name', 'error'
     * @param string $subfolder Target folder (e.g. 'rolls', 'bills')
     * @param array $allowedExts Permitted extensions
     * @return string|null Web-accessible URL or relative path, or null on failure
     */
    public static function saveUploadedFile(array $file, string $subfolder = 'rolls', array $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf']): ?string {
        if (empty($file['tmp_name']) || empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpPath  = $file['tmp_name'];
        $origName = $file['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!empty($allowedExts) && !in_array($ext, $allowedExts, true)) {
            return null;
        }

        // 1. Try Cloudinary first
        if (self::isConfigured()) {
            $cloudRes = self::uploadFile($tmpPath, $origName, "invento/{$subfolder}");
            if (!empty($cloudRes['secure_url'])) {
                return $cloudRes['secure_url'];
            }
        }

        // 2. Graceful fallback to local disk storage
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/' . $subfolder;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $prefix   = strtoupper(substr($subfolder, 0, 4));
        $safeName = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . ($ext ? '.' . $ext : '');
        $dest     = $uploadDir . '/' . $safeName;

        if (is_uploaded_file($tmpPath)) {
            if (move_uploaded_file($tmpPath, $dest)) {
                return 'storage/uploads/' . $subfolder . '/' . $safeName;
            }
        } else {
            // For testing or non-HTTP-POST temporary files
            if (copy($tmpPath, $dest)) {
                return 'storage/uploads/' . $subfolder . '/' . $safeName;
            }
        }

        return null;
    }

    /**
     * Resolve any image or document path into an absolute browser-ready URL.
     * - Returns Cloudinary URLs (http:// or https://) untouched.
     * - Converts local paths ('storage/uploads/...') into full application URLs via Controller::url().
     * - Returns default fallback if empty.
     *
     * @param string|null $path Stored path or Cloudinary URL
     * @param string $default Fallback URL if path is empty
     * @return string
     */
    public static function url(?string $path, string $default = ''): string {
        $trimmed = trim((string)$path);
        if ($trimmed === '') {
            return $default;
        }

        // Already a full Cloudinary or external URL
        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        // Local storage path
        $cleanPath = '/' . ltrim($trimmed, '/');
        return Controller::url($cleanPath);
    }

    /**
     * Check if a given path is hosted on Cloudinary
     */
    public static function isCloudinaryUrl(?string $path): bool {
        $trimmed = trim((string)$path);
        return preg_match('#^https?://#i', $trimmed) === 1 && str_contains($trimmed, 'cloudinary.com');
    }
}
