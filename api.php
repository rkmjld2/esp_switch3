<?php

require_once "db.php";

header("Content-Type: application/json");

/*
 * ESP8266 calls:
 *
 * api.php?action=get
 *
 * to obtain D1-D8 values.
 */

if (isset($_GET["action"]) && $_GET["action"] === "get") {

    /*
     * Update controller heartbeat.
     */
    $heartbeat = $conn->query(
        "UPDATE controller_status
         SET last_seen = NOW()
         WHERE id = 1"
    );

    if (!$heartbeat) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "error" => "Unable to update controller heartbeat"
        ]);

        exit;
    }

    /*
     * Read D1-D8 values.
     */
    $result = $conn->query(
        "SELECT D1,D2,D3,D4,D5,D6,D7,D8
         FROM esp_control
         WHERE id = 1"
    );

    if (!$result) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "error" => $conn->error
        ]);

        exit;
    }

    $row = $result->fetch_assoc();

    if (!$row) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "error" => "Controller record not found"
        ]);

        exit;
    }

    /*
     * Send pin states to ESP8266.
     */
    echo json_encode([
        "success" => true,
        "D1" => intval($row["D1"]),
        "D2" => intval($row["D2"]),
        "D3" => intval($row["D3"]),
        "D4" => intval($row["D4"]),
        "D5" => intval($row["D5"]),
        "D6" => intval($row["D6"]),
        "D7" => intval($row["D7"]),
        "D8" => intval($row["D8"])
    ]);

    exit;
}

/*
 * Invalid request.
 */
http_response_code(400);

echo json_encode([
    "success" => false,
    "error" => "Invalid API request"
]);

?>
