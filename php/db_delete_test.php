<?php

require "db_connect.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"];

    $sql = "DELETE FROM students
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":id" => $id
    ]);

    $message = "Student record deleted successfully!";
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Delete Student</title>
</head>

<body>

<h2>Delete Student</h2>

<?php

if ($message != "") {
    echo "<p>" . $message . "</p>";
}

?>

<form method="POST">

    <label>Student ID:</label><br>
    <input type="number" name="id" required>
    <br><br>

    <button type="submit">Delete Student</button>

</form>

</body>

</html>