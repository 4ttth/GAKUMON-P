<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/config.php';

if (!isset($_SESSION['sUser'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

date_default_timezone_set('Asia/Manila');
$username = $_SESSION['sUser'];

// Get user_id
$stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}
$userID = $row['user_id'];

// Fetch current pet energy and last update
$sql = "SELECT user_pet_id, energy_level, last_energy_update
        FROM tbl_user_pet
        WHERE user_id = ? LIMIT 1";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$petResult = $stmt->get_result();

if (!$pet = $petResult->fetch_assoc()) {
    echo json_encode(['ok' => false, 'error' => 'No pet found']);
    exit;
}

$currentEnergy = (float)$pet['energy_level'];
$lastUpdate = strtotime($pet['last_energy_update']);
$now = time();

// Calculate hours since last update
$hoursPassed = max(0, ($now - $lastUpdate) / 3600);

// Only apply decay if time has actually passed AND if more than 10 seconds have elapsed
// This prevents immediate decay after feeding/updating energy
if ($hoursPassed > 0.003) { // 0.003 hours = ~10 seconds threshold
    // Calculate decay (4.17% per hour)
    $decayPerHour = 4.17;
    $decay = $hoursPassed * $decayPerHour;
   
    $newEnergy = max(0, $currentEnergy - $decay);
   
    // Update DB with new energy and current timestamp
    $updateSql = "UPDATE tbl_user_pet
                  SET energy_level = ?, last_energy_update = NOW()
                  WHERE user_pet_id = ?";
    $updateStmt = $connection->prepare($updateSql);
    $updateStmt->bind_param("di", $newEnergy, $pet['user_pet_id']);
    $updateStmt->execute();
   
    echo json_encode([
        'ok' => true,
        'energy' => round($newEnergy, 2),
        'debug' => [
            'current' => $currentEnergy,
            'hours_passed' => round($hoursPassed, 2),
            'decay' => round($decay, 2),
            'new' => round($newEnergy, 2),
            'action' => 'decay_applied'
        ]
    ]);
} else {
    // No time passed, return current energy without updating
    echo json_encode([
        'ok' => true,
        'energy' => round($currentEnergy, 2),
        'debug' => [
            'current' => $currentEnergy,
            'hours_passed' => round($hoursPassed, 2),
            'action' => 'no_decay_needed'
        ]
    ]);
}
