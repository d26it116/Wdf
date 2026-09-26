
<?php

// Check whether form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get and sanitize data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $mobile = trim($_POST["mobile"]);
    $password = trim($_POST["password"]);
    $confirmPassword = trim($_POST["confirmPassword"]);
    $course = trim($_POST["course"]);
    $year = trim($_POST["year"]);

    // Gender
    if (isset($_POST["gender"])) {
        $gender = trim($_POST["gender"]);
    } else {
        $gender = "";
    }


    // Server-side validation

    if ($name == "") {
        die("Error: Name is required.");
    }

    if ($email == "") {
        die("Error: Email is required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Error: Invalid email address.");
    }

    if ($mobile == "") {
        die("Error: Mobile number is required.");
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        die("Error: Mobile number must contain 10 digits.");
    }

    if ($password == "") {
        die("Error: Password is required.");
    }

    if (strlen($password) < 6) {
        die("Error: Password must contain at least 6 characters.");
    }

    if ($password != $confirmPassword) {
        die("Error: Passwords do not match.");
    }

    if ($course == "") {
        die("Error: Please select a course.");
    }

    if ($year < 1 || $year > 4) {
        die("Error: Year must be between 1 and 4.");
    }

    if ($gender == "") {
        die("Error: Please select gender.");
    }

    if (!isset($_POST["terms"])) {
        die("Error: Please accept the terms and conditions.");
    }


    // Sanitize data before storing

    $name = htmlspecialchars($name);
    $email = htmlspecialchars($email);
    $mobile = htmlspecialchars($mobile);
    $course = htmlspecialchars($course);
    $year = htmlspecialchars($year);
    $gender = htmlspecialchars($gender);


    // Create student record

    $student = [
        "name" => $name,
        "email" => $email,
        "mobile" => $mobile,
        "course" => $course,
        "year" => $year,
        "gender" => $gender
    ];

    

    // JSON file location

    $file = "../data/students.json";


    // Read existing JSON data

    if (file_exists($file)) {

        $data = json_decode(
            file_get_contents($file),
            true
        );

    } else {

        $data = [];
    }


    // Add new record

    $data[] = $student;


    // Save data into JSON file

    if (
        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT)
        )
    ) {

        echo "<h2>Registration Successful!</h2>";

        echo "<p>Your registration has been submitted successfully.</p>";

        echo '<a href="register.html">Back to Registration</a>';

    } else {

        echo "<h2>Error</h2>";

        echo "<p>Unable to save registration data.</p>";
    }

}

else {

    echo "<h2>Error</h2>";

    echo "<p>Invalid request.</p>";
}

?>

