<?php
// ---------- Session + database connection + shared helpers ----------
if (session_status() === PHP_SESSION_NONE) { session_start(); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // errors become exceptions we can trap
try {
    $conn = mysqli_connect("localhost", "root", "", "theyos_db");
    mysqli_set_charset($conn, "utf8mb4");
} catch (mysqli_sql_exception $ex) {
    error_log($ex->getMessage());
    die("<h3>Cannot connect to the database.</h3><p>Start MySQL in XAMPP and import <b>sql/theyos_db.sql</b> in phpMyAdmin.</p>");
}

function e($s)          { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n)       { return '₱' . number_format((float)$n, 2); }
function redirect($url) { header("Location: $url"); exit; }
function flash($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }

// ---------- Session / role guards ----------
function require_login() { if (!isset($_SESSION['account_id'])) { redirect('index.php'); } }
function require_admin() {
    require_login();
    if ($_SESSION['accesslevel'] !== 'admin') { flash('danger', 'Access denied. Administrators only.'); redirect('dashboard.php'); }
}

// ---------- Prepared-statement helpers ----------
function q($sql, $types = '', $params = []) {          // run a prepared query, returns the statement
    global $conn;
    $st = mysqli_prepare($conn, $sql);
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
    mysqli_stmt_execute($st);
    return $st;
}
function rows($st) { return mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC); }
function one($sql, $types = '', $params = []) { $r = rows(q($sql, $types, $params)); return $r[0] ?? null; }
function val($sql) { global $conn; $r = mysqli_fetch_row(mysqli_query($conn, $sql)); return $r[0] ?? 0; }

function status_badge($s) {
    $map = ['Pending' => 'warning text-dark', 'Preparing' => 'info text-dark', 'Completed' => 'success', 'Cancelled' => 'secondary'];
    return "<span class='badge bg-" . ($map[$s] ?? 'dark') . "'>" . e($s) . "</span>";
}
function db_error_message($ex, $fkMessage = 'This record is in use by other records and cannot be deleted.') {
    error_log($ex->getMessage());
    if ($ex->getCode() == 1451) return $fkMessage;                  // foreign key restrict
    if ($ex->getCode() == 1062) return 'Duplicate entry: that value already exists.';
    return 'A database error occurred. Please try again.';
}
