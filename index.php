<?php
/*
 * ESP_SWITCH3 - index.php
 * Database: esp_switch3
 * Tables: controllers, esp_control
 * No controller_status table is required.
 */

session_start();
require_once "db.php";

/* Logout */
if (isset($_GET["logout"])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

/* Select controller */
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["select_controller_id"])) {

    $id = trim($_POST["select_controller_id"]);

    if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $id)) {
        $_SESSION["controller_id"] = $id;
    }

    header("Location: index.php");
    exit;
}

$controller_id = $_SESSION["controller_id"] ?? "";

/* Controller list */
$controllers = [];

$result = $conn->query(
    "SELECT controller_id, customer_name, active, last_seen
     FROM controllers ORDER BY id"
);

while ($row = $result->fetch_assoc()) {
    $controllers[] = $row;
}

/* First page: select controller */
if ($controller_id === "") {
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ESP8266 Controllers</title>
<style>
body{font-family:Arial;background:#eee;padding:20px}
.box{max-width:850px;margin:40px auto;background:#fff;padding:25px;
border-radius:15px;text-align:center;box-shadow:0 3px 15px #aaa}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border:1px solid #ddd}
button{padding:9px 18px;border:0;border-radius:6px;background:#06c;color:#fff}
</style>
</head>
<body>
<div class="box">
<h1>ESP8266 Controllers</h1>
<p>Select the controller you want to operate.</p>
<table>
<tr>
<th>Controller ID</th>
<th>Customer</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach ($controllers as $row):
    $online = false;
    if (!empty($row["last_seen"])) {
        $t = strtotime($row["last_seen"]);
        $online = $t !== false && time() - $t <= 15;
    }
?>
<tr>
<td><?= htmlspecialchars($row["controller_id"]) ?></td>
<td><?= htmlspecialchars($row["customer_name"] ?? "") ?></td>
<td>
<?php
if ((int)$row["active"] !== 1) {
    echo "INACTIVE";
} else {
    echo $online ? "ONLINE" : "OFFLINE";
}
?>
</td>
<td>
<?php if ((int)$row["active"] === 1): ?>
<form method="post">
<input type="hidden" name="select_controller_id"
       value="<?= htmlspecialchars($row["controller_id"]) ?>">
<button type="submit">CONTROL</button>
</form>
<?php else: ?>
Inactive
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>
</body>
</html>
<?php
exit;
}

/* Validate selected ID */
if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $controller_id)) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

/* Get controller */
$stmt = $conn->prepare(
    "SELECT controller_id, customer_name, active, last_seen
     FROM controllers WHERE controller_id=? LIMIT 1"
);
$stmt->bind_param("s", $controller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

$controller = $result->fetch_assoc();
$stmt->close();

if ((int)$controller["active"] !== 1) {
    die("<h2 style='text-align:center'>Controller is inactive</h2>");
}

/* Process D1-D8 and ALL ON/OFF */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["pin"], $_POST["value"])) {

        $allowed = ["D1","D2","D3","D4","D5","D6","D7","D8"];
        $pin = strtoupper(trim($_POST["pin"]));
        $value = (int)$_POST["value"];

        if (in_array($pin, $allowed, true) &&
            ($value === 0 || $value === 1)) {

            $stmt = $conn->prepare(
                "UPDATE esp_control SET `$pin`=? WHERE controller_id=?"
            );
            $stmt->bind_param("is", $value, $controller_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (isset($_POST["all_on"])) {
        $stmt = $conn->prepare(
            "UPDATE esp_control
             SET D1=1,D2=1,D3=1,D4=1,D5=1,D6=1,D7=1,D8=1
             WHERE controller_id=?"
        );
        $stmt->bind_param("s", $controller_id);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST["all_off"])) {
        $stmt = $conn->prepare(
            "UPDATE esp_control
             SET D1=0,D2=0,D3=0,D4=0,D5=0,D6=0,D7=0,D8=0
             WHERE controller_id=?"
        );
        $stmt->bind_param("s", $controller_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: index.php");
    exit;
}

/* Read D1-D8 */
$stmt = $conn->prepare(
    "SELECT D1,D2,D3,D4,D5,D6,D7,D8
     FROM esp_control WHERE controller_id=? LIMIT 1"
);
$stmt->bind_param("s", $controller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("<h2 style='text-align:center'>Control data not found</h2>");
}

$control = $result->fetch_assoc();
$stmt->close();

/* Online status from controllers.last_seen */
$online = false;
if (!empty($controller["last_seen"])) {
    $t = strtotime($controller["last_seen"]);
    $online = $t !== false && time() - $t <= 15;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="5">
<title>ESP8266 D1-D8 Control</title>
<style>
body{font-family:Arial;background:#eee;padding:20px}
.box{max-width:850px;margin:auto;background:#fff;padding:25px;
border-radius:15px;text-align:center;box-shadow:0 3px 15px #aaa}
.info{background:#f5f5f5;padding:15px;border-radius:10px}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-top:25px}
.pin{padding:18px;background:#f7f7f7;border:1px solid #ddd;border-radius:12px}
.on{color:green;font-weight:bold}.off{color:red;font-weight:bold}
button{padding:10px 16px;margin:4px;border:0;border-radius:7px;color:#fff;cursor:pointer}
.onb{background:green}.offb{background:red}.all{background:#06c;padding:12px 25px}
.link{display:inline-block;margin-top:20px;padding:10px 20px;
background:#555;color:#fff;text-decoration:none;border-radius:7px}
@media(max-width:650px){.grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="box">

<h1>ESP8266 D1-D8 Controller</h1>

<div class="info">
<p><b>Controller ID:</b> <?= htmlspecialchars($controller_id) ?></p>
<p><b>Customer:</b> <?= htmlspecialchars($controller["customer_name"] ?? "") ?></p>
<p><b>Controller Status:</b>
<span class="<?= $online ? "on" : "off" ?>">
<?= $online ? "ONLINE" : "OFFLINE" ?>
</span></p>
<p><b>Last Seen:</b>
<?= htmlspecialchars($controller["last_seen"] ?? "Not available") ?></p>
</div>

<form method="post">
<button class="all" name="all_on" value="1">ALL ON</button>
<button class="all" name="all_off" value="1">ALL OFF</button>
</form>

<div class="grid">
<?php for ($i=1; $i<=8; $i++):
    $pin = "D".$i;
    $state = (int)$control[$pin];
?>
<div class="pin">
<h3><?= $pin ?></h3>
<p class="<?= $state ? "on" : "off" ?>">
<?= $state ? "ON" : "OFF" ?>
</p>

<form method="post">
<input type="hidden" name="pin" value="<?= $pin ?>">
<button class="onb" name="value" value="1">ON</button>
<button class="offb" name="value" value="0">OFF</button>
</form>
</div>
<?php endfor; ?>
</div>

<a class="link" href="index.php">BACK TO CONTROLLERS</a>
<a class="link" href="index.php?logout=1">LOGOUT</a>

</div>
</body>
</html>
