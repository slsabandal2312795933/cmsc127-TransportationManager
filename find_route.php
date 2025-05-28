<?php
include 'DBConnector.php'; // Your existing DBConnector.php

// Get landmarks for dropdown
$landmarks = [];
$result = $conn->query("SELECT LandmarkID, LandmarkName FROM Landmarks ORDER BY Distance ASC");
while ($row = $result->fetch_assoc()) {
    $landmarks[] = $row;
}

// Passenger types
$passengerTypes = ['Regular', 'Elderly', 'Student', 'Disabled'];

// AC Types
$acTypes = ['AC', 'Non-AC'];

$errors = [];
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup = intval($_POST['pickup'] ?? 0);
    $dropoff = intval($_POST['dropoff'] ?? 0);
    $acType = $_POST['actype'] ?? '';
    $passengerType = $_POST['passengertype'] ?? '';

    // Basic validation
    if ($pickup == 0 || $dropoff == 0 || $pickup == $dropoff) {
        $errors[] = "Please select different Pickup and Drop-off points.";
    }
    if (!in_array($acType, $acTypes)) {
        $errors[] = "Please select a valid AC type.";
    }
    if (!in_array($passengerType, $passengerTypes)) {
        $errors[] = "Please select a valid Passenger type.";
    }

    if (empty($errors)) {
        $sql = "
            SELECT j.JeepID, j.JeepName, 
                   l1.Distance AS PickupDist, l2.Distance AS DropoffDist,
                   f.FarePerKM, f.MinimumFare
            FROM Jeep j
            JOIN jeepLandmarks jl1 ON j.JeepID = jl1.JeepID
            JOIN jeepLandmarks jl2 ON j.JeepID = jl2.JeepID
            JOIN Landmarks l1 ON jl1.LandmarkID = l1.LandmarkID
            JOIN Landmarks l2 ON jl2.LandmarkID = l2.LandmarkID
            JOIN jeepFare jf ON j.JeepID = jf.JeepID
            JOIN Fare f ON jf.FareID = f.FareID AND f.PassengerType = ?
            WHERE jl1.LandmarkID = ? AND jl2.LandmarkID = ?
            ORDER BY j.JeepName ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("sii", $passengerType, $pickup, $dropoff);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $distance = abs($row['DropoffDist'] - $row['PickupDist']);
                $fare = $distance * floatval($row['FarePerKM']);
                if ($fare < floatval($row['MinimumFare'])) {
                    $fare = floatval($row['MinimumFare']);
                }
                if ($acType === 'AC') {
                    $fare *= 1.2; // 20% surcharge for AC
                }

                $results[] = [
                    'JeepName' => $row['JeepName'],
                    'Distance' => round($distance, 2),
                    'Fare' => round($fare, 2),
                    'PassengerType' => $passengerType,
                    'ACType' => $acType
                ];
            }

            if (empty($results)) {
                $errors[] = "No jeep routes found covering both selected points for your criteria.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Route Finder - Sakay na Iloilo!</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        form label { display: block; margin-top: 1rem; }
        select, button { margin-top: 0.5rem; padding: 0.3rem; width: 250px; }
        table { border-collapse: collapse; margin-top: 2rem; width: 100%; max-width: 700px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f4f4f4; }
        .errors { color: red; margin-top: 1rem; }
        header nav a { text-decoration: none; color: #0066cc; }
        header nav a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header>
        <h1>Route Finder</h1>
        <nav><a href="index.php">Back to Home</a></nav>
    </header>

    <section>
        <form method="POST" action="">
            <label for="pickup">Pickup Point:</label>
            <select id="pickup" name="pickup" required>
                <option value="">-- Select Pickup --</option>
                <?php foreach ($landmarks as $lm): ?>
                    <option value="<?= $lm['LandmarkID'] ?>" <?= (isset($_POST['pickup']) && $_POST['pickup'] == $lm['LandmarkID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lm['LandmarkName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="dropoff">Drop-off Point:</label>
            <select id="dropoff" name="dropoff" required>
                <option value="">-- Select Drop-off --</option>
                <?php foreach ($landmarks as $lm): ?>
                    <option value="<?= $lm['LandmarkID'] ?>" <?= (isset($_POST['dropoff']) && $_POST['dropoff'] == $lm['LandmarkID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lm['LandmarkName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="actype">AC Type:</label>
            <select id="actype" name="actype" required>
                <option value="">-- Select AC Type --</option>
                <?php foreach ($acTypes as $type): ?>
                    <option value="<?= $type ?>" <?= (isset($_POST['actype']) && $_POST['actype'] === $type) ? 'selected' : '' ?>>
                        <?= $type ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="passengertype">Passenger Type:</label>
            <select id="passengertype" name="passengertype" required>
                <option value="">-- Select Passenger Type --</option>
                <?php foreach ($passengerTypes as $ptype): ?>
                    <option value="<?= $ptype ?>" <?= (isset($_POST['passengertype']) && $_POST['passengertype'] === $ptype) ? 'selected' : '' ?>>
                        <?= $ptype ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Find Routes</button>
        </form>
    </section>

    <section>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <h2>Available Jeep Routes and Fare Estimates</h2>
            <table>
                <thead>
                    <tr>
                        <th>Jeep Name</th>
                        <th>Distance (KM)</th>
                        <th>Fare (PHP)</th>
                        <th>Passenger Type</th>
                        <th>AC Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['JeepName']) ?></td>
                            <td><?= $r['Distance'] ?></td>
                            <td><?= number_format($r['Fare'], 2) ?></td>
                            <td><?= htmlspecialchars($r['PassengerType']) ?></td>
                            <td><?= htmlspecialchars($r['ACType']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</body>
</html>
