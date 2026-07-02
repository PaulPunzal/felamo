<?php
/**
 * backend/services/R2UploadService.php
 *
 * Thin wrapper around Aws\S3\S3Client configured to talk to Cloudflare R2
 * (which is S3-API compatible).
 *
 * SCOPE: This service is used ONLY for Aralin (lesson) video assets.
 * Profile pictures, avatars, certificates, and smes_img uploads must
 * continue to use local server storage (backend/storage/...) untouched.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\ObjectUploader;
use Aws\Exception\MultipartUploadException;

class R2UploadService
{
    private S3Client $s3;
    private string $bucket;
    private string $publicBaseUrl;
    private string $videoPrefix;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/r2.php';

        foreach (['access_key_id', 'secret_access_key', 'endpoint', 'bucket', 'public_base_url'] as $required) {
            if (empty($config[$required]) || str_starts_with($config[$required], 'YOUR_')) {
                throw new \RuntimeException(
                    "R2 configuration is incomplete. Please set '$required' in backend/config/r2.php " .
                    "or via environment variables before uploading videos."
                );
            }
        }

        $this->bucket        = $config['bucket'];
        $this->publicBaseUrl = rtrim($config['public_base_url'], '/');
        $this->videoPrefix   = trim($config['video_prefix'] ?? 'aralin-videos', '/');

        $this->s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => $config['region'],
            'endpoint'                => $config['endpoint'],
            'use_path_style_endpoint' => false,
            'credentials'             => [
                'key'    => $config['access_key_id'],
                'secret' => $config['secret_access_key'],
            ],
        ]);
    }

    /**
     * Generate a unique, collision-safe object key for a new video upload.
     */
    public function generateVideoKey(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension)) : '.mp4';

        $unique = date('Ymd_His') . '_' . bin2hex(random_bytes(8));

        return $this->videoPrefix . '/' . $unique . $extension;
    }

    /**
     * Step 1 of the browser-side multipart flow: open an upload session.
     */
    public function createMultipartUpload(string $key, string $contentType): array
    {
        $result = $this->s3->createMultipartUpload([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'ContentType' => $contentType ?: 'video/mp4',
        ]);

        return [
            'key'       => $key,
            'upload_id' => $result['UploadId'],
        ];
    }

    /**
     * Step 2: a presigned PUT URL the browser can upload a single part to
     * directly, without the video bytes ever passing through our PHP server.
     */
    public function getPresignedPartUrl(string $key, string $uploadId, int $partNumber, int $expiresInSeconds = 3600): string
    {
        $command = $this->s3->getCommand('UploadPart', [
            'Bucket'     => $this->bucket,
            'Key'        => $key,
            'UploadId'   => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        $presignedRequest = $this->s3->createPresignedRequest($command, "+{$expiresInSeconds} seconds");

        return (string) $presignedRequest->getUri();
    }

    /**
     * Step 3: finalize the multipart upload once every part has been PUT.
     * $parts = [['PartNumber' => 1, 'ETag' => '"..."'], ...]
     *
     * Returns the public URL to store in `aralin.attachment_filename`.
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): string
    {
        usort($parts, fn($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

        $this->s3->completeMultipartUpload([
            'Bucket'          => $this->bucket,
            'Key'             => $key,
            'UploadId'        => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);

        return $this->getPublicUrl($key);
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $this->s3->abortMultipartUpload([
            'Bucket'   => $this->bucket,
            'Key'      => $key,
            'UploadId' => $uploadId,
        ]);
    }

    /**
     * Server-side upload used by the CLI migration script. Automatically
     * uses multipart upload under the hood for large files via ObjectUploader.
     *
     * @param resource $resource An open file handle/stream.
     */
    public function putObjectStream(string $key, $resource, string $contentType): void
    {
        $uploader = new ObjectUploader(
            $this->s3,
            $this->bucket,
            $key,
            $resource,
            'private',
            ['params' => ['ContentType' => $contentType ?: 'video/mp4']]
        );

        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            // Attempt to clean up a partially-created multipart upload
            $uploadId = $e->getState()->getUploadId();
            if ($uploadId) {
                try {
                    $this->abortMultipartUpload($key, $uploadId);
                } catch (\Throwable $ignored) {
                }
            }
            throw new \RuntimeException('R2 multipart upload failed: ' . $e->getMessage());
        }
    }

    public function deleteObject(string $key): void
    {
        $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
    }

    public function getPublicUrl(string $key): string
    {
        return $this->publicBaseUrl . '/' . ltrim($key, '/');
    }

    /**
     * Given a full public URL we previously issued, recover the object key
     * so we can delete it. Returns null for URLs we don't manage
     * (e.g. legacy Cloudinary URLs still sitting in old rows).
     */
    public function extractKeyFromUrl(?string $url): ?string
    {
        if (empty($url) || strpos($url, $this->publicBaseUrl) !== 0) {
            return null;
        }

        return ltrim(substr($url, strlen($this->publicBaseUrl)), '/');
    }
}
