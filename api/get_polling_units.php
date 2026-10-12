<?php
// api/get_polling_units.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$lga_id = filter_input(INPUT_GET, 'lga_id', FILTER_VALIDATE_INT);
$ward_id = filter_input(INPUT_GET, 'ward_id', FILTER_VALIDATE_INT);

if (!$lga_id) {
    echo json_encode(['success' => false, 'message' => 'LGA ID is required', 'data' => []]);
    exit;
}

try {
    if ($ward_id) {
        $stmt = $pdo->prepare("
            SELECT uniqueid, polling_unit_id, polling_unit_name, polling_unit_number 
            FROM polling_unit 
            WHERE lga_id = ? AND ward_id = ? 
            ORDER BY polling_unit_name ASC
        ");
        $stmt->execute([$lga_id, $ward_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT uniqueid, polling_unit_id, polling_unit_name, polling_unit_number 
            FROM polling_unit 
            WHERE lga_id = ? 
            ORDER BY polling_unit_name ASC
        ");
        $stmt->execute([$lga_id]);
    }

    $units = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $units
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error querying polling units: ' . $e->getMessage()
    ]);
}
