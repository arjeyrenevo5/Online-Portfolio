<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['feedback_visitor'] ??= bin2hex(random_bytes(32));
$_SESSION['feedback_csrf'] ??= bin2hex(random_bytes(32));

function send_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function database(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'portfolio_feedback';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function current_feedback(PDO $db): array
{
    $visitor = (string) $_SESSION['feedback_visitor'];
    $ratingQuery = $db->prepare('SELECT rating FROM portfolio_ratings WHERE project_key = ? AND visitor_key = ?');
    $ratingQuery->execute(['arjey-portfolio', $visitor]);

    $commentQuery = $db->prepare('SELECT id, visitor_key, comment_text, created_at FROM portfolio_comments WHERE project_key = ? ORDER BY created_at DESC, id DESC LIMIT 100');
    $commentQuery->execute(['arjey-portfolio']);

    $comments = array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'comment' => $row['comment_text'],
        'created_at' => $row['created_at'],
        'is_mine' => hash_equals($visitor, (string) $row['visitor_key']),
    ], $commentQuery->fetchAll());

    return [
        'rating' => $ratingQuery->fetchColumn() ?: null,
        'comments' => $comments,
    ];
}

function request_body(): array
{
    $body = json_decode((string) file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}

try {
    $db = database();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        send_json(['ok' => true, 'csrf' => $_SESSION['feedback_csrf']] + current_feedback($db));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['ok' => false, 'message' => 'Use GET or POST for feedback.'], 405);
    }

    $input = request_body();
    if (!hash_equals((string) $_SESSION['feedback_csrf'], (string) ($input['csrf'] ?? ''))) {
        send_json(['ok' => false, 'message' => 'Your session expired. Reload the page and try again.'], 403);
    }

    $visitor = (string) $_SESSION['feedback_visitor'];
    $action = (string) ($input['action'] ?? '');

    if ($action === 'rate') {
        $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            send_json(['ok' => false, 'message' => 'Choose a rating from 1 to 5.'], 422);
        }
        $query = $db->prepare('INSERT INTO portfolio_ratings (project_key, visitor_key, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP');
        $query->execute(['arjey-portfolio', $visitor, $rating]);
        $message = 'Your rating was saved.';
    } elseif ($action === 'clear_rating') {
        $query = $db->prepare('DELETE FROM portfolio_ratings WHERE project_key = ? AND visitor_key = ?');
        $query->execute(['arjey-portfolio', $visitor]);
        $message = 'Your rating was removed.';
    } elseif ($action === 'add_comment') {
        $comment = trim((string) ($input['comment'] ?? ''));
        if ($comment === '' || mb_strlen($comment) > 300) {
            send_json(['ok' => false, 'message' => 'Write a comment of 1 to 300 characters.'], 422);
        }
        $query = $db->prepare('INSERT INTO portfolio_comments (project_key, visitor_key, comment_text) VALUES (?, ?, ?)');
        $query->execute(['arjey-portfolio', $visitor, $comment]);
        $message = 'Your comment was posted.';
    } elseif ($action === 'edit_comment') {
        $commentId = filter_var($input['comment_id'] ?? null, FILTER_VALIDATE_INT);
        $comment = trim((string) ($input['comment'] ?? ''));
        if (!$commentId || $comment === '' || mb_strlen($comment) > 300) {
            send_json(['ok' => false, 'message' => 'Enter a comment of 1 to 300 characters.'], 422);
        }
        $query = $db->prepare('UPDATE portfolio_comments SET comment_text = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND project_key = ? AND visitor_key = ?');
        $query->execute([$comment, $commentId, 'arjey-portfolio', $visitor]);
        if (!$query->rowCount()) {
            send_json(['ok' => false, 'message' => 'Comment not found or no changes made.'], 404);
        }
        $message = 'Your comment was updated.';
    } elseif ($action === 'delete_comment') {
        $commentId = filter_var($input['comment_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$commentId) {
            send_json(['ok' => false, 'message' => 'Choose a valid comment.'], 422);
        }
        $query = $db->prepare('DELETE FROM portfolio_comments WHERE id = ? AND project_key = ? AND visitor_key = ?');
        $query->execute([$commentId, 'arjey-portfolio', $visitor]);
        if (!$query->rowCount()) {
            send_json(['ok' => false, 'message' => 'Comment not found.'], 404);
        }
        $message = 'Your comment was deleted.';
    } else {
        send_json(['ok' => false, 'message' => 'Unknown feedback action.'], 400);
    }

    send_json(['ok' => true, 'message' => $message, 'csrf' => $_SESSION['feedback_csrf']] + current_feedback($db));
} catch (Throwable $error) {
    error_log($error->getMessage());
    send_json(['ok' => false, 'message' => 'Feedback could not be saved. Check the PHP and MySQL setup.'], 503);
}
