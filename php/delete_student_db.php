<?php

require "db_connect.php";

$id = 3;

$sql = "DELETE FROM students
        WHERE id = :id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":id" => $id
]);

echo "Student deleted successfully!";

?>