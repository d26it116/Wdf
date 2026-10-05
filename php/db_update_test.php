<?php

require "db_connect.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"];
    $email = $_POST["email"];

    $sql = "UPDATE students
            SET email = :email
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":email" => $email,
        ":id" => $id
    ]);

    $message = "Student record updated successfully!";

}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Update Student</title>
</head>

<body>

<h2>Update Student Email</h2>

<?php

if ($message != "") {
    echo "<p>" . $message . "</p>";
}

?>

<form method="POST">

    <label>Student ID:</label><br>
    <input type="number" name="id" required>
    <br><br>

    <label>New Email:</label><br>
    <input type="email" name="email" required>
    <br><br>

    <button type="submit">Update Student</button>

</form>

</body>

</html>