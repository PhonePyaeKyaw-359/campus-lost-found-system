<?php
declare(strict_types=1);

final class PhotoStorage
{
    /** @param array<string, mixed>|null $upload */
    public static function storeLostPhoto(?array $upload): ?string
    {
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new DomainException('The photo could not be uploaded. Please try another image.');
        }

        $size = $upload['size'] ?? null;
        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_int($size) || $size < 1 || $size > MAX_UPLOAD_BYTES
            || !is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new DomainException('The photo must be a JPG or PNG image no larger than 2 MB.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($temporaryPath),
            'image/png' => @imagecreatefrompng($temporaryPath),
            default => false,
        };
        if ($image === false) {
            throw new DomainException('The photo must be a valid JPG or PNG image.');
        }

        $extension = $mimeType === 'image/jpeg' ? 'jpg' : 'png';
        $relativePath = 'lost/lost_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $storageDirectory = dirname(__DIR__) . '/uploads/lost';
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            imagedestroy($image);
            throw new RuntimeException('The upload storage directory is unavailable.');
        }

        $destination = dirname(__DIR__) . '/uploads/' . $relativePath;
        if ($mimeType === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagepng($image, $destination, 6);
        } else {
            $saved = imagejpeg($image, $destination, 90);
        }
        imagedestroy($image);

        if (!$saved) {
            throw new RuntimeException('The photo could not be stored.');
        }
        chmod($destination, 0600);

        return $relativePath;
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || !preg_match('#^lost/lost_[a-f0-9]{32}\.(jpg|png)$#', $relativePath)) {
            return;
        }

        $path = dirname(__DIR__) . '/uploads/' . $relativePath;
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function absoluteLostPath(string $relativePath): ?string
    {
        if (!preg_match('#^lost/lost_[a-f0-9]{32}\.(jpg|png)$#', $relativePath)) {
            return null;
        }

        $path = dirname(__DIR__) . '/uploads/' . $relativePath;
        return is_file($path) ? $path : null;
    }
}
