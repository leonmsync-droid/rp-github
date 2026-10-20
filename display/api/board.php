<?php
declare(strict_types=1);

// Live leaderboard updates for search and weight sliders: rendered rows, so they match the first page load.

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$board = Board::latest();
$launched = count(Snapshots::dates()) >= (int) config('product.launch_days') || getenv('RP_PREVIEW');
if (!$board || !$launched) {
    http_response_code(503);
    echo json_encode(['error' => 'No data yet']);
    exit;
}

$lang = isset($_GET['lang']) && is_string($_GET['lang']) && isset($board['languages'][$_GET['lang']]) ? $_GET['lang'] : null;
$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr($_GET['q'], 0, 80) : '';
$w = isset($_GET['w']) && is_array($_GET['w']) ? $_GET['w'] : [];
$result = Board::query($board, $lang, $q, $w);

$summary = View::num($result['total']) . ' repos'
    . ($lang ? ' in ' . View::e($lang) : '')
    . ($q !== '' ? ' matching “' . View::e($q) . '”' : '')
    . ($result['custom'] ? ' · your weights' : '');

echo json_encode([
    'summary' => $summary,
    'html' => implode('', array_map(fn($i) => View::row($i, $result['custom']), $result['rows'])),
    'foot' => 'Showing the top ' . min(Board::PAGE, $result['total']) . ' of ' . View::num($result['total']),
    'total' => $result['total'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
