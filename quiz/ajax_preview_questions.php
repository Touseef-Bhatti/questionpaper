<?php
// ajax_preview_questions.php
// Fetches questions based on host configuration for preview
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../auth/auth_check.php';
include '../db_connect.php';

header('Content-Type: application/json');

try {
    $questions = [];
    
    // Check if topics are provided
    $topicsJson = $_POST['topics'] ?? '[]';
    $topics = json_decode($topicsJson, true);
    
    if (!empty($topics)) {
        // Fetch questions by topics
        $placeholders = str_repeat('?,', count($topics) - 1) . '?';
        $types = str_repeat('s', count($topics));
        $params = $topics;
        
        // 1. Get from standard MCQs table
        $sql = "SELECT 'standard' as source, mcq_id, question, option_a, option_b, option_c, option_d, correct_option 
                FROM mcqs 
                WHERE topic IN ($placeholders) 
                ORDER BY mcq_id DESC LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();

        // 1b. Get MCQs generated from uploaded books by matching question text or chapter name.
        $bookConditions = [];
        $bookParams = [];
        $bookTypes = "";
        foreach ($topics as $topicName) {
            $bookConditions[] = "(m.question LIKE ? OR c.chapter_name LIKE ?)";
            $like = "%" . trim((string)$topicName) . "%";
            $bookParams[] = $like;
            $bookParams[] = $like;
            $bookTypes .= "ss";
        }

        $bookSql = "SELECT 'book' as source, CONCAT('book_', m.mcq_id) as mcq_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option
                    FROM mcqs_from_book m
                    LEFT JOIN chapter c ON c.chapter_id = m.chapter_id
                    WHERE " . implode(" OR ", $bookConditions) . "
                    ORDER BY m.mcq_id DESC LIMIT 100";
        $stmt = $conn->prepare($bookSql);
        if ($stmt) {
            $stmt->bind_param($bookTypes, ...$bookParams);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            $stmt->close();
        }
        
        // 2. Get from AI Generated MCQs table
        $aiSql = "SELECT 'ai' as source, id as mcq_id, question_text as question, option_a, option_b, option_c, option_d, correct_option 
                  FROM AIGeneratedMCQs 
                  WHERE topic IN ($placeholders) 
                  ORDER BY id DESC LIMIT 50";
                  
        $stmt = $conn->prepare($aiSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
        
    } else {
        // Fetch by Class/Book/Chapter
        $classId = intval($_POST['class_id'] ?? 0);
        $bookId = intval($_POST['book_id'] ?? 0);
        $chapterIdsStr = $_POST['chapter_ids'] ?? '';
        
        if ($classId > 0 && $bookId > 0) {
            $sql = "SELECT 'standard' as source, mcq_id, question, option_a, option_b, option_c, option_d, correct_option 
                    FROM mcqs 
                    WHERE class_id = ? AND book_id = ?";
            
            $types = "ii";
            $params = [$classId, $bookId];
            
            if (!empty($chapterIdsStr)) {
                $chapterIds = array_map('intval', explode(',', $chapterIdsStr));
                if (!empty($chapterIds)) {
                    $placeholders = str_repeat('?,', count($chapterIds) - 1) . '?';
                    $sql .= " AND chapter_id IN ($placeholders)";
                    $types .= str_repeat('i', count($chapterIds));
                    $params = array_merge($params, $chapterIds);
                }
            }
            
            $sql .= " ORDER BY mcq_id DESC LIMIT 100";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            $stmt->close();

            $bookSql = "SELECT 'book' as source, CONCAT('book_', mcq_id) as mcq_id, question, option_a, option_b, option_c, option_d, correct_option
                    FROM mcqs_from_book
                    WHERE class_id = ? AND book_id = ?";

            $bookTypes = "ii";
            $bookParams = [$classId, $bookId];

            if (!empty($chapterIdsStr)) {
                $chapterIds = array_map('intval', explode(',', $chapterIdsStr));
                if (!empty($chapterIds)) {
                    $placeholders = str_repeat('?,', count($chapterIds) - 1) . '?';
                    $bookSql .= " AND chapter_id IN ($placeholders)";
                    $bookTypes .= str_repeat('i', count($chapterIds));
                    $bookParams = array_merge($bookParams, $chapterIds);
                }
            }

            $bookSql .= " ORDER BY mcq_id DESC LIMIT 100";

            $stmt = $conn->prepare($bookSql);
            if ($stmt) {
                $stmt->bind_param($bookTypes, ...$bookParams);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $questions[] = $row;
                }
                $stmt->close();
            }
        }
    }
    
    echo json_encode(['success' => true, 'questions' => $questions, 'count' => count($questions)]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
