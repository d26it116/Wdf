<?php
// StudentHub/php/get_events.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db_connect_mysqli.php';

// Check which columns exist in your events table
$columns = [];
$resCols = $conn->query("SHOW COLUMNS FROM events");
if ($resCols) {
    while ($col = $resCols->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    $resCols->free();
}

$hasCategory = in_array('category', $columns, true);
$hasLocation = in_array('location', $columns, true);
$hasPoster   = in_array('poster', $columns, true);

// Select columns with safe fallbacks
$catCol    = $hasCategory ? "COALESCE(category, 'Technical')" : "'Technical'";
$locCol    = $hasLocation ? "COALESCE(location, 'Campus')" : "'Campus'";
$posterCol = $hasPoster   ? "poster" : "NULL";

$sql = "SELECT 
            event_id,
            event_name AS name,
            DATE_FORMAT(event_date, '%e %M %Y') AS date,
            {$catCol} AS category,
            {$locCol} AS location,
            description,
            {$posterCol} AS poster
        FROM events
        ORDER BY event_date ASC";

$result = $conn->query($sql);
$events = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $result->free();
}

echo json_encode($events);