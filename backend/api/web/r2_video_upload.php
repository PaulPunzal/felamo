<?php
/**
 * backend/api/web/r2_video_upload.php
 *
 * Endpoint used ONLY by the Aralin (lesson video) uploader in
 * pages/scripts/levelsDetails.js. It brokers a direct-to-Cloudflare-R2
 * multipart upload so large video files never have to pass through PHP.
 *
 * DO NOT reuse this endpoint for profile pictures, avatars, certificates,
 * or smes_img uploads — those keep using local storage as-is.
 *
 * requestType values:
 *   InitiateMultipartUpload  { filename, content_type }
 *   GetUploadPartUrl         { key, upload_id, part_number }
 *   CompleteMultipartUpload  { key, upload_id, parts (JSON array) }
 *   AbortMultipartUpload     { key, upload_id }
 *   DeleteVideo              { key }   (admin cleanup / manual use)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// --- Auth guard: only logged-in web users (teachers/admins) may upload lesson videos ---
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unauthorized. Please log in again.',
    ]);
    exit;
}

require_once __DIR__ . '/../../services/R2UploadService.php';

$requestType = $_POST['requestType'] ?? '';

try {
    $r2 = new R2UploadService();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'R2 is not configured correctly: ' . $e->getMessage(),
    ]);
    exit;
}

switch ($requestType) {

    case 'InitiateMultipartUpload': {
        $filename    = trim($_POST['filename'] ?? 'video.mp4');
        $contentType = trim($_POST['content_type'] ?? 'video/mp4');

        try {
            $key = $r2->generateVideoKey($filename);
            $session = $r2->createMultipartUpload($key, $contentType);

            echo json_encode([
                'status'    => 'success',
                'key'       => $session['key'],
                'upload_id' => $session['upload_id'],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to initiate upload: ' . $e->getMessage()]);
        }
        break;
    }

    case 'GetUploadPartUrl': {
        $key        = trim($_POST['key'] ?? '');
        $uploadId   = trim($_POST['upload_id'] ?? '');
        $partNumber = (int) ($_POST['part_number'] ?? 0);

        if (empty($key) || empty($uploadId) || $partNumber < 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'key, upload_id, and part_number are required.']);
            break;
        }

        try {
            $url = $r2->getPresignedPartUrl($key, $uploadId, $partNumber);
            echo json_encode(['status' => 'success', 'url' => $url]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to get part URL: ' . $e->getMessage()]);
        }
        break;
    }

    case 'CompleteMultipartUpload': {
        $key      = trim($_POST['key'] ?? '');
        $uploadId = trim($_POST['upload_id'] ?? '');
        $partsRaw = $_POST['parts'] ?? '[]';
        $parts    = json_decode($partsRaw, true);

        if (empty($key) || empty($uploadId) || !is_array($parts) || empty($parts)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'key, upload_id, and parts are required.']);
            break;
        }

        // Normalize part entries defensively
        $cleanParts = [];
        foreach ($parts as $p) {
            if (!isset($p['PartNumber'], $p['ETag'])) continue;
            $cleanParts[] = [
                'PartNumber' => (int) $p['PartNumber'],
                'ETag'       => $p['ETag'],
            ];
        }

        try {
            $publicUrl = $r2->completeMultipartUpload($key, $uploadId, $cleanParts);
            echo json_encode(['status' => 'success', 'url' => $publicUrl]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to complete upload: ' . $e->getMessage()]);
        }
        break;
    }

    case 'AbortMultipartUpload': {
        $key      = trim($_POST['key'] ?? '');
        $uploadId = trim($_POST['upload_id'] ?? '');

        if (empty($key) || empty($uploadId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'key and upload_id are required.']);
            break;
        }

        try {
            $r2->abortMultipartUpload($key, $uploadId);
            echo json_encode(['status' => 'success', 'message' => 'Upload aborted.']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to abort upload: ' . $e->getMessage()]);
        }
        break;
    }

    case 'DeleteVideo': {
        $key = trim($_POST['key'] ?? '');

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'key is required.']);
            break;
        }

        try {
            $r2->deleteObject($key);
            echo json_encode(['status' => 'success', 'message' => 'Video deleted from R2.']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete video: ' . $e->getMessage()]);
        }
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing requestType.']);
}
