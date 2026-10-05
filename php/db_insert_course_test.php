<?php

require "db_connect.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $mobile = $_POST["mobile"];
    $course_id = $_POST["course_id"];
    $year = $_POST["year"];
    $gender = $_POST["gender"];

    $sql = "INSERT INTO students
            (name, email, mobile, course_id, year, gender)
            VALUES
            (:name, :email, :mobile, :course_id, :year, :gender)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":name" => $name,
        ":email" => $email,
        ":mobile" => $mobile,
        ":course_id" => $course_id,
        ":year" => $year,
        ":gender" => $gender
    ]);

    $message = "Student inserted successfully!";
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Insert Student with Course</title>
</head>

<body>

<h2>Student Registration - MySQL</h2>

<?php

if ($message != "") {
    echo "<p>" . $message . "</p>";
}

?>

<form method="POST">

    <label>Name:</label><br>
    <input type="text" name="name" required>
    <br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required>
    <br><br>

    <label>Mobile:</label><br>
    <input type="text" name="mobile" required>
    <br><br>

    <label>Course:</label><br>

    <select name="course_id" required>

        <option value="">Select Course</option>

        <?php

        $courseQuery = $pdo->query(
            "SELECT course_id, course_name FROM courses"
        );

        while ($course = $courseQuery->fetch(PDO::FETCH_ASSOC)) {

            echo "<option value='" . $course["course_id"] . "'>";
            echo $course["course_name"];
            echo "</option>";

        }

        ?>

    </select>

    <br><br>

    <label>Year:</label><br>
    <input type="number" name="year" required>
    <br><br>

    <label>Gender:</label><br>

    <select name="gender" required>

        <option value="">Select</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>

    </select>

    <br><br>

    <button type="submit">Save Student</button>

</form>

</body>

</html>