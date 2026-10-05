<?php

require "db_connect.php";

$sql = "SELECT
            students.id,
            students.name,
            students.email,
            students.mobile,
            courses.course_name,
            students.year,
            students.gender
        FROM students
        INNER JOIN courses
        ON students.course_id = courses.course_id";

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

    <h1>Registered Students</h1>

    <table border="1" cellpadding="10">

        <tr>

            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Course</th>
            <th>Year</th>
            <th>Gender</th>

        </tr>

        <?php foreach ($students as $student) { ?>

            <tr>

                <td><?php echo $student["id"]; ?></td>

                <td><?php echo $student["name"]; ?></td>

                <td><?php echo $student["email"]; ?></td>

                <td><?php echo $student["mobile"]; ?></td>

                <td><?php echo $student["course_name"]; ?></td>

                <td><?php echo $student["year"]; ?></td>

                <td><?php echo $student["gender"]; ?></td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>