<?php

require "db_connect.php";

$sql = "SELECT * FROM students";

$stmt = $pdo->prepare($sql);

$stmt->execute();

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Student Records</title>
</head>

<body>

<h2>Student Records</h2>

<table border="1" cellpadding="8">

    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Mobile</th>
        <th>Course</th>
        <th>Year</th>
        <th>Gender</th>
    </tr>

    <?php foreach ($students as $student): ?>

    <tr>
        <td><?php echo $student["id"]; ?></td>
        <td><?php echo $student["name"]; ?></td>
        <td><?php echo $student["email"]; ?></td>
        <td><?php echo $student["mobile"]; ?></td>
        <td><?php echo $student["course"]; ?></td>
        <td><?php echo $student["year"]; ?></td>
        <td><?php echo $student["gender"]; ?></td>
    </tr>

    <?php endforeach; ?>

</table>

</body>

</html>