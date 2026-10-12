<?php
// api/get_wards.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$lga_id = filter_input(INPUT_GET, 'lga_id', FILTER_VALIDATE_INT);

if (!$lga_id) {
    echo json_encode(['success' => false, 'message' => 'Valid LGA ID is required', 'data' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT ward_id, ward_name, uniqueid 
        FROM ward 
        WHERE lga_id = ? 
        ORDER BY ward_name ASC
    ");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $wards
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error querying wards: ' . $e->getMessage()
    ]);
}
