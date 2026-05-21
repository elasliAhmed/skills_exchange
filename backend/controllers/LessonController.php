<?php
// Lesson Controller
require_once __DIR__ . '/../models/Lesson.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

class LessonController {
    private $lesson;
    private $enrollment;

    public function __construct($database) {
        $this->lesson     = new Lesson($database);
        $this->enrollment = new Enrollment($database);
    }

    /**
     * POST /lessons.php/comment
     * Body: { lesson_id, teacher_comment }
     */
    public function saveComment($data) {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        if (empty($data['lesson_id']) || !trim($data['teacher_comment'] ?? '')) {
            $this->sendResponse(false, ['lesson_id and a non-empty comment are required'], 400);
            return;
        }

        $lesson = $this->lesson->getByIdForTeacher((int)$data['lesson_id'], $user_id);
        if (!$lesson) {
            $this->sendResponse(false, ['Lesson not found or you are not the teacher for this offer'], 404);
            return;
        }

        $ok = $this->lesson->saveComment((int)$data['lesson_id'], trim($data['teacher_comment']));
        if ($ok) {
            $updated = $this->lesson->getById((int)$data['lesson_id']);
            $this->sendResponse(true, [
                'message' => 'Comment saved',
                'lesson'  => $updated,
            ]);
        } else {
            $this->sendResponse(false, ['Failed to save comment'], 500);
        }
    }

    /**
     * GET /lessons.php/enrollment/{enrollment_id}
     * Returns all lessons, owner, and final-comment for an enrollment — owner teacher or enrolled student only
     */
    public function getByEnrollment($enrollment_id) {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $db  = new Database();
        $cn  = $db->connect();

        // Fetch enrollment + owner + learner
        $row = $cn->prepare(
            "SELECT e.id,
                    e.learner_id,
                    e.offer_id,
                    e.final_teacher_comment,
                    e.final_comment_created_at,
                    e.status        AS enrollment_status,
                    e.remaining_lessons,
                    us.user_id AS teacher_id,
                    u.username AS teacher_username,
                    u.full_name AS teacher_full_name,
                    us.skill_name AS course_title,
                    lr.username AS student_username,
                    lr.full_name AS student_full_name
               FROM enrollments e
               JOIN user_skills us ON e.offer_id = us.id
               JOIN users u ON us.user_id = u.id
               JOIN users lr ON e.learner_id = lr.id
              WHERE e.id = ?"
        );
        $row->execute([$enrollment_id]);
        $enrollData = $row->fetch(PDO::FETCH_ASSOC);

        if (!$enrollData) {
            $this->sendResponse(false, ['Enrollment not found'], 404);
            return;
        }

        $isLearner = (int)$enrollData['learner_id'] === (int)$user_id;
        $isTeacher = (int)$enrollData['teacher_id'] === (int)$user_id;
        if (!$isLearner && !$isTeacher) {
            $this->sendResponse(false, ['Unauthorized — not a participant in this enrollment'], 403);
            return;
        }

        $lessons = $this->lesson->getByEnrollmentId((int)$enrollment_id);

        $studentName = trim(($enrollData['student_full_name'] ?? '') . ' (@' . ($enrollData['student_username'] ?? '') . ')');
        if ($studentName === '(@)') $studentName = 'Unknown';

        unset($enrollData['teacher_id']);

        $this->sendResponse(true, [
            'enrollment_id'            => $enrollData['id'],
            'student_name'             => $studentName,
            'teacher_username'         => $enrollData['teacher_username'],
            'teacher_full_name'        => $enrollData['teacher_full_name'],
            'course_title'             => $enrollData['course_title'],
            'enrollment_status'        => $enrollData['enrollment_status'],
            'final_teacher_comment'    => $enrollData['final_teacher_comment'],
            'final_comment_created_at' => $enrollData['final_comment_created_at'],
            'lessons'                  => $lessons,
            'lessons_count'            => $lessons ? count($lessons) : 0,
        ]);
    }

    private function sendResponse($success, $data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'data'    => $data,
        ]);
    }
}
