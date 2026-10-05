<?php

require "db_connect.php";

$id = 3;

$new_email = "priyam_updated@gmail.com";

$sql = "UPDATE students
        SET email = :email
        WHERE id = :id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":email" => $new_email,
    ":id" => $id
]);

echo "Student email updated successfully!";

?>