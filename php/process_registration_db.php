<?php

require "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $mobile = trim($_POST["mobile"]);
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

    echo "<h2>Registration Successful!</h2>";
    echo "<p>Student data has been saved to MySQL.</p>";
    echo "<a href='register_db.html'>Register Another Student</a>";

}

?>