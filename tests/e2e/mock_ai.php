<?php
// Fake Claude / Gemini endpoint for the AI suite (run.php starts it: php -S 127.0.0.1:8899 mock_ai.php).
// The question text drives it: "TOOL <name> <path>" asks for that tool call first, "FAIL" answers 401.
$body = json_decode(file_get_contents('php://input'), true) ?? [];
file_put_contents(sys_get_temp_dir() . '/phaierp-e2e-mock.json', json_encode(['uri' => $_SERVER['REQUEST_URI'], 'headers' => getallheaders(), 'body' => $body], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
header('Content-Type: application/json');
$gemini = str_contains($_SERVER['REQUEST_URI'], 'generateContent');

if ($gemini) {
    $contents = $body['contents'];
    $first = '';
    foreach ($contents as $c) {
        if ($c['role'] === 'user' && isset($c['parts'][0]['text'])) $first = $c['parts'][0]['text'];
    }
    $last = end($contents);
    $result = $last['parts'][0]['functionResponse']['response']['result'] ?? null;
} else {
    $msgs = $body['messages'];
    $first = '';
    foreach ($msgs as $m) {
        if ($m['role'] === 'user' && is_string($m['content'])) $first = $m['content'];
    }
    $last = end($msgs);
    $result = is_array($last['content']) ? ($last['content'][0]['content'] ?? null) : null;
}

if (str_contains($first, 'FAIL')) {
    http_response_code(401);
    echo json_encode(['error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
    exit;
}
$call = null;
if ($result === null && preg_match('/TOOL (\w+) (\S+)/', $first, $m)) {
    $call = ['name' => $m[1], 'args' => ['path' => $m[2]]];
}
$text = $result === null ? 'สวัสดี ไม่ต้องใช้ข้อมูล' : "ผลลัพธ์:\n" . $result;

if ($gemini) {
    $parts = $call ? [['functionCall' => $call]] : [['text' => $text]];
    echo json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => $parts]]],
        'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 100]], JSON_UNESCAPED_UNICODE);
} else {
    $content = $call ? [['type' => 'text', 'text' => 'กำลังดูข้อมูล'], ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $call['name'], 'input' => $call['args']]]
        : [['type' => 'text', 'text' => $text]];
    echo json_encode(['content' => $content, 'stop_reason' => $call ? 'tool_use' : 'end_turn',
        'usage' => ['input_tokens' => 1000, 'output_tokens' => 100]], JSON_UNESCAPED_UNICODE);
}
