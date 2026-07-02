<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class AralinController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetAralins($level_id)
    {
        $q = $this->conn->prepare("
        SELECT *
        FROM `aralin`
        WHERE level_id = ?");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $level_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $aralins = [];

            while ($row = $result->fetch_assoc()) {
                $aralins[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $aralins
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }

    /**
     * Best-effort delete of a video previously stored on Cloudflare R2.
     * Silently no-ops for legacy Cloudinary URLs or if R2 isn't configured,
     * so this NEVER blocks an Insert/Edit from succeeding.
     */
    private function deleteR2VideoIfManaged($url)
    {
        if (empty($url)) {
            return;
        }

        try {
            require_once __DIR__ . '/../services/R2UploadService.php';
            $r2 = new R2UploadService();

            $key = $r2->extractKeyFromUrl($url);
            if ($key !== null) {
                $r2->deleteObject($key);
            }
        } catch (\Throwable $e) {
            // Intentionally swallow: cleanup failures should never break
            // the lesson save flow. Old/orphaned R2 objects can be cleaned
            // up separately if needed.
        }
    }

    public function InsertAralin($post) // video_url comes from the R2 multipart upload done client-side
    {
        $title = $post['title'] ?? '';
        $summary = $post['summary'] ?? '';
        $details = $post['details'] ?? '';
        $level_id = $post['level_id'] ?? null;
        $video_url = $post['video_url'] ?? ''; // Now receiving the R2 public URL from JS

        if (empty($video_url)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Video URL from R2 is missing.'
            ]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT MAX(aralin_no) FROM aralin WHERE level_id = ?");
        $stmt->bind_param("i", $level_id);
        $stmt->execute();

        $max_aralin_no = 0;
        $stmt->bind_result($max_aralin_no);
        $stmt->fetch();
        $stmt->close();

        $next_aralin_no = ($max_aralin_no ?? 0) + 1;

        $stmt = $this->conn->prepare("
        INSERT INTO aralin (aralin_no, aralin_title, summary, details, attachment_filename, level_id)
        VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
            return;
        }

        // We save the full R2 public URL into attachment_filename
        $stmt->bind_param("issssi", $next_aralin_no, $title, $summary, $details, $video_url, $level_id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Aralin inserted successfully.',
                'aralin_id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
        }
        $stmt->close();
    }

    public function EditAralin($post) // video_url (optional) comes from a new R2 multipart upload done client-side
    {
        $aralin_id = $post['aralin_id'] ?? null;
        $title = $post['title'] ?? '';
        $summary = $post['summary'] ?? '';
        $details = $post['details'] ?? '';
        $level_id = $post['level_id'] ?? null;
        $video_url = $post['video_url'] ?? ''; // The new R2 URL (if the user uploaded a replacement video)

        if (!$aralin_id || !$level_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing Aralin ID or Level ID.'
            ]);
            return;
        }

        // Fetch current attachment filename/URL so we don't lose it if they only updated text
        $stmt = $this->conn->prepare("SELECT attachment_filename FROM aralin WHERE id = ? AND level_id = ?");
        $stmt->bind_param("ii", $aralin_id, $level_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Aralin not found.'
            ]);
            return;
        }

        $stmt->bind_result($old_filename);
        $stmt->fetch();
        $stmt->close();

        // If a new video URL was provided from the frontend, use it.
        // Otherwise, keep the old filename/URL.
        $isReplacingVideo = !empty($video_url) && $video_url !== $old_filename;
        $new_filename = $isReplacingVideo ? $video_url : $old_filename;

        $stmt = $this->conn->prepare("
        UPDATE aralin 
        SET aralin_title = ?, summary = ?, details = ?, attachment_filename = ? 
        WHERE id = ? AND level_id = ?
        ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare update statement.'
            ]);
            return;
        }

        $stmt->bind_param("ssssii", $title, $summary, $details, $new_filename, $aralin_id, $level_id);

        if ($stmt->execute()) {
            // Clean up the OLD R2 object now that the row points at the new one.
            // No-ops safely for legacy Cloudinary URLs or unmanaged assets.
            if ($isReplacingVideo) {
                $this->deleteR2VideoIfManaged($old_filename);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Aralin updated successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Update failed: ' . $stmt->error
            ]);
        }

        $stmt->close();
    }

    public function GetDoneAralin($userId)
    {
        $q = $this->conn->prepare("
            SELECT antas.level, a.aralin_no, a.aralin_title AS title, da.completed_at
            FROM `student_aralin_progress` AS da
            JOIN `aralin` AS a ON da.aralin_id = a.id
            JOIN `levels` AS antas ON a.level_id = antas.id
            WHERE da.user_id = ?
        ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $userId);

        if ($q->execute()) {
            $result = $q->get_result();
            $doneAralins = [];

            while ($row = $result->fetch_assoc()) {
                $doneAralins[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $doneAralins
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }

    public function GetWatchHistory($aralinId)
    {
        $q = $this->conn->prepare("
        SELECT u.first_name, u.last_name, u.lrn, da.completed_at FROM `student_aralin_progress` AS da
        JOIN `aralin` AS a ON da.aralin_id = a.id
        JOIN `users` AS u ON da.user_id = u.id
        WHERE da.aralin_id = ?
        ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $aralinId);

        if ($q->execute()) {
            $result = $q->get_result();
            $doneAralins = [];

            while ($row = $result->fetch_assoc()) {
                $doneAralins[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $doneAralins
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }
}