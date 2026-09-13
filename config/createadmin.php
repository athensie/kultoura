<?php
$conn = new mysqli("localhost", "root", "", "kultoura_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin details
$first_name = "Athena Grace";
$last_name  = "Macahia";
$username   = "admin";
$email      = "athenamacahia17@gmail.com";
$password   = password_hash("admin123", PASSWORD_DEFAULT);
$role       = "Super Admin";

// Check if admin already exists
$check = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ?");
$check->bind_param("ss", $username, $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo "Admin account already exists!";
} else {

    $stmt = $conn->prepare("
        INSERT INTO admins
        (first_name, last_name, username, email, password, role)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssss",
        $first_name,
        $last_name,
        $username,
        $email,
        $password,
        $role
    );

    if ($stmt->execute()) {
        echo "Super Admin account created successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$check->close();
$conn->close();
?>