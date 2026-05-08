<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/app.php';

// $path = '../config/app.php';

// echo "Path : $path";

// require "$path";

function register($name, $email, $password){
    global $conn;

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){
        return false;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role)
        VALUES (?, ?, ?, 'customer')
    ");

    $stmt->bind_param("sss", $name, $email, $hashedPassword);

    return $stmt->execute();
}

function login($username, $password){
    global $conn;

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE name = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    // ssssssesssionnnnn set
    if (!isset($_SESSION['auth_roles']) || !is_array($_SESSION['auth_roles'])) {
        $_SESSION['auth_roles'] = [];
    }

    $_SESSION['auth_roles'][$user['role']] = [
        'user_id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];

    return true;
}

function logout($role = null, $redirectPath = '../public/login.php'){
    if (!isset($_SESSION['auth_roles']) || !is_array($_SESSION['auth_roles'])) {
        $_SESSION['auth_roles'] = [];
    }

    $role = $role ?? ($_SESSION['role'] ?? null);

    if ($role !== null && isset($_SESSION['auth_roles'][$role])) {
        unset($_SESSION['auth_roles'][$role]);
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === $role) {
        unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['name']);
    }

    if (empty($_SESSION['auth_roles'])) {
        session_unset();
        session_destroy();
    }

    header('Location: ' . $redirectPath);
    exit;
}
?>
