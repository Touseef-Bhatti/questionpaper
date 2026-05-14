<?php
include __DIR__ . '/../db_connect.php';

$data = [];

// Fetch classes 9 and 10
$classes = $conn->query("SELECT * FROM class WHERE class_id IN (9, 10)");
while ($class = $classes->fetch_assoc()) {
    $class_id = $class['class_id'];
    $data[$class_id] = [
        'name' => $class['class_name'],
        'books' => []
    ];

    // Fetch books for this class
    $books = $conn->query("SELECT * FROM book WHERE class_id = $class_id");
    while ($book = $books->fetch_assoc()) {
        $book_id = $book['book_id'];
        $data[$class_id]['books'][$book_id] = [
            'name' => $book['book_name'],
            'chapters' => []
        ];

        // Fetch chapters for this book
        $chapters = $conn->query("SELECT * FROM chapter WHERE book_id = $book_id ORDER BY chapter_no ASC, chapter_id ASC");
        while ($chapter = $chapters->fetch_assoc()) {
            $data[$class_id]['books'][$book_id]['chapters'][] = [
                'chapter_id' => $chapter['chapter_id'],
                'chapter_no' => $chapter['chapter_no'],
                'chapter_name' => $chapter['chapter_name']
            ];
        }
    }
}

// Generate the variable form (PHP code)
header('Content-Type: text/plain');
echo "<?php\n";
echo "\$educational_data = " . var_export($data, true) . ";\n";
echo "?>";
?>
