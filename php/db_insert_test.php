<?php

require "db_connect.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $mobile = $_POST["mobile"];
    $course = $_POST["course"];
    $year = $_POST["year"];
    $gender = $_POST["gender"];

    $sql = "INSERT INTO students
            (name, email, mobile, course, year, gender)
            VALUES
            (:name, :email, :mobile, :course, :year, :gender)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":name" => $name,
        ":email" => $email,
        ":mobile" => $mobile,
        ":course" => $course,
        ":year" => $year,
        ":gender" => $gender
    ]);

    $message = "Student inserted successfully!";

}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Insert Student</title>
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
    <input type="text" name="course" required>
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