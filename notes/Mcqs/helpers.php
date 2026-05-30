<?php
function alh_mcqs_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', (string) $slug);
    return trim((string) $slug, '-');
}

function alh_mcqs_abs_url(string $path): string
{
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $protocol = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'ahmadlearninghub.com.pk';
    return $protocol . '://' . $host . '/' . ltrim($path, '/');
}

function alh_mcqs_class_url(int $classId): string
{
    return "/class-{$classId}-all-subjects-mcqs-with-explanations";
}

function alh_mcqs_book_url(int $classId, string $bookName): string
{
    return "/class-{$classId}-" . alh_mcqs_slug($bookName) . "-chapter-wise-mcqs-with-explanations";
}

function alh_mcqs_chapter_url(int $classId, string $bookName, array $chapter): string
{
    $chapterNo = (int) ($chapter['chapter_no'] ?? 0);
    $chapterName = (string) ($chapter['chapter_name'] ?? '');
    $chapterPart = $chapterNo > 0 ? "chapter-{$chapterNo}-{$chapterName}" : $chapterName;
    return "/class-{$classId}-" . alh_mcqs_slug($bookName) . "-" . alh_mcqs_slug($chapterPart) . "-mcqs-with-explanations";
}

function alh_mcqs_find_book(mysqli $conn, int $classId, string $bookSlug): ?array
{
    $stmt = $conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_id ASC");
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($book = $result->fetch_assoc()) {
        if (alh_mcqs_slug((string) $book['book_name']) === alh_mcqs_slug($bookSlug)) {
            $stmt->close();
            return $book;
        }
    }
    $stmt->close();
    return null;
}

function alh_mcqs_find_chapter(mysqli $conn, int $classId, int $bookId, string $chapterSlug): ?array
{
    $stmt = $conn->prepare("SELECT chapter_id, chapter_no, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_id ASC");
    $stmt->bind_param('ii', $classId, $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($chapter = $result->fetch_assoc()) {
        $chapterNo = (int) ($chapter['chapter_no'] ?? 0);
        $chapterName = (string) ($chapter['chapter_name'] ?? '');
        $chapterPart = $chapterNo > 0 ? "chapter-{$chapterNo}-{$chapterName}" : $chapterName;
        if (alh_mcqs_slug($chapterPart) === alh_mcqs_slug($chapterSlug)) {
            $stmt->close();
            return $chapter;
        }
    }
    $stmt->close();
    return null;
}

function alh_mcqs_split_book_chapter_from_path(mysqli $conn, int $classId, string $path): ?array
{
    $pathSlug = alh_mcqs_slug($path);
    if ($pathSlug === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY LENGTH(book_name) DESC, book_id ASC");
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($book = $result->fetch_assoc()) {
        $bookSlug = alh_mcqs_slug((string) $book['book_name']);
        if ($bookSlug === '') {
            continue;
        }

        if ($pathSlug === $bookSlug) {
            $stmt->close();
            return ['book' => $book, 'chapter_slug' => ''];
        }

        $prefix = $bookSlug . '-';
        if (str_starts_with($pathSlug, $prefix)) {
            $stmt->close();
            return [
                'book' => $book,
                'chapter_slug' => substr($pathSlug, strlen($prefix))
            ];
        }
    }
    $stmt->close();
    return null;
}

function alh_mcqs_correct_letter(array $mcq): string
{
    $options = [
        'A' => $mcq['option_a'] ?? '',
        'B' => $mcq['option_b'] ?? '',
        'C' => $mcq['option_c'] ?? '',
        'D' => $mcq['option_d'] ?? '',
    ];
    $correct = trim((string) ($mcq['correct_option'] ?? ''));
    $upper = strtoupper($correct);
    if (isset($options[$upper])) {
        return $upper;
    }
    foreach ($options as $letter => $text) {
        if (strcasecmp(trim((string) $text), $correct) === 0) {
            return $letter;
        }
    }
    return '';
}
?>
