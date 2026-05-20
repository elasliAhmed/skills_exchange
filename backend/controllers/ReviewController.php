<?php
// Review Controller
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/LessonRequest.php';
require_once __DIR__ . '/../config/jwt.php';

class ReviewController {
    private $review;
    private $lessonRequest;

    public function __construct($database) {
        $this->review = new Review($database);
        $this->lessonRequest = new LessonRequest($database);
    }

    public function createReview($data) {
        $reviewer_id = JWT::getUserId();
        if (!$reviewer_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $required = ['lesson_request_id', 'rating'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(false, ["$field is required"], 400);
                return;
            }
        }

        if ($data['rating'] < 1 || $data['rating'] > 5) {
            $this->sendResponse(false, ['Rating must be between 1 and 5'], 400);
            return;
        }

        $lesson = $this->lessonRequest->findById($data['lesson_request_id']);
        if (!$lesson) {
            $this->sendResponse(false, ['Lesson request not found'], 404);
            return;
        }

        if ($lesson['status'] !== 'completed') {
            $this->sendResponse(false, ['Can only review completed lessons'], 400);
            return;
        }

        if ($this->review->hasReviewed($data['lesson_request_id'], $reviewer_id)) {
            $this->sendResponse(false, ['Already reviewed this lesson'], 400);
            return;
        }

        $reviewed_id = ($lesson['requester_id'] == $reviewer_id) ? $lesson['teacher_id'] : $lesson['requester_id'];

        $this->review->create(
            $reviewer_id,
            $reviewed_id,
            $data['lesson_request_id'],
            $data['rating'],
            $data['comment'] ?? null
        );

        $this->sendResponse(true, ['message' => 'Review submitted successfully']);
    }

    public function getUserReviews($user_id) {
        $reviews = $this->review->getUserReviews($user_id);
        $this->sendResponse(true, ['reviews' => $reviews]);
    }

    public function getAverageRating($user_id) {
        $rating = $this->review->getAverageRating($user_id);
        $this->sendResponse(true, [
            'average_rating' => round($rating['average'], 1),
            'total_reviews' => $rating['count']
        ]);
    }

    private function sendResponse($success, $data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'data' => $data
        ]);
    }
}