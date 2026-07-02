<?php
/**
 * backend/config/r2.php
 *
 * Cloudflare R2 (S3-compatible) configuration.
 *
 * SCOPE: This config is used ONLY for Aralin (lesson) video uploads.
 * Do NOT wire this into profile pictures, avatars, certificates, or
 * smes_img uploads — those must remain on local server storage
 * (backend/storage/...) exactly as they are today.
 */

require_once __DIR__ . '/env.php';

return [
    // Cloudflare account id (found in the R2 dashboard URL / API tab)
    'account_id' => app_env('R2_ACCOUNT_ID', 'YOUR_CLOUDFLARE_ACCOUNT_ID'),

    // R2 API token credentials (create under R2 -> Manage R2 API Tokens)
    'access_key_id'     => app_env('R2_ACCESS_KEY_ID', 'YOUR_R2_ACCESS_KEY_ID'),
    'secret_access_key' => app_env('R2_SECRET_ACCESS_KEY', 'YOUR_R2_SECRET_ACCESS_KEY'),

    // The bucket that will hold ONLY Aralin lesson videos
    'bucket' => app_env('R2_BUCKET', 'felamo-videos'),

    // R2 has a single logical region
    'region' => 'auto',

    // S3-compatible endpoint for your account
    // Format: https://<ACCOUNT_ID>.r2.cloudflarestorage.com
    'endpoint' => app_env('R2_ENDPOINT', 'https://YOUR_CLOUDFLARE_ACCOUNT_ID.r2.cloudflarestorage.com'),

    // Public URL base the videos will be served from.
    // Either R2's "Public Development URL" (https://pub-xxxx.r2.dev)
    // or a custom domain you've mapped to the bucket
    // (e.g. https://videos.felamo.example.com). No trailing slash.
    'public_base_url' => app_env('R2_PUBLIC_BASE_URL', 'https://videos.felamo.example.com'),

    // Folder/prefix inside the bucket where lesson videos live
    'video_prefix' => 'aralin-videos',
];