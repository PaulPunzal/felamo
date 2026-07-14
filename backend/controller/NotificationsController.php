<?php
include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class NotificationsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function GetCreatedNotification($created_by)
    {
        $q = $this->conn->prepare("
        SELECT n.id, n.section_id, n.title, n.description, s.section_name
        FROM `notifications` AS n
        JOIN sections AS s ON n.section_id = s.id
        WHERE n.created_by = ?");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $created_by);

        if ($q->execute()) {
            $result = $q->get_result();
            $notification = [];

            while ($row = $result->fetch_assoc()) {
                $notification[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $notification
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }

    public function CreateNotification($title, $description, $created_by, $section_id)
    {
        // --- DEDUP CHECK: same title + description to same section in last 24 hrs ---
        $dupCheck = $this->conn->prepare("
            SELECT id FROM notifications
            WHERE title       = ?
            AND description = ?
            AND section_id  = ?
            AND created_at  >= NOW() - INTERVAL 24 HOUR
            LIMIT 1
        ");

        if (!$dupCheck) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $dupCheck->bind_param("ssi", $title, $description, $section_id);
        $dupCheck->execute();
        $dupCheck->store_result();

        if ($dupCheck->num_rows > 0) {
            echo json_encode([
                'status'  => 'duplicate',
                'message' => 'An identical notification was already sent to this section in the last 24 hours.'
            ]);
            $dupCheck->close();
            return;
        }
        $dupCheck->close();
        // --- END DEDUP CHECK ---

        $q = $this->conn->prepare("
            INSERT INTO notifications (title, description, section_id, created_by)
            VALUES (?, ?, ?, ?)
        ");

        if (!$q) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("ssii", $title, $description, $section_id, $created_by);

        if ($q->execute()) {
            echo json_encode([
                'status'  => 'success',
                'message' => 'Notification created.',
            ]);
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Execute failed: ' . $q->error,
            ]);
        }

        $q->close();
    }

    public function GetNotificationReadStatus($notification_id, $section_id)
    {
        $q = $this->conn->prepare("
            SELECT u.first_name, u.last_name, u.lrn,
                   IF(nr.read_at IS NOT NULL, 1, 0) as is_read,
                   nr.read_at
            FROM student_teacher_assignments sta
            JOIN users u ON sta.student_lrn = u.lrn
            LEFT JOIN notification_reads nr ON nr.student_lrn = u.lrn AND nr.notification_id = ?
            WHERE sta.section_id = ?
            ORDER BY u.last_name ASC
        ");

        if (!$q) {
            echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $this->conn->error]);
            return;
        }

        $q->bind_param("ii", $notification_id, $section_id);
        
        if ($q->execute()) {
            $result = $q->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $q->error]);
        }
        $q->close();
    }
}
