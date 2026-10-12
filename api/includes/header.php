<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = "INEC Election Results Portal";
}
if (!isset($active_tab)) {
    $active_tab = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Bincom Election System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php">
            <span class="badge bg-success p-2 rounded-circle"><i class="bi bi-box-seam-fill"></i></span>
            <span>INEC Election Results System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_tab === 'q1') ? 'active fw-bold' : '' ?>" href="index.php">
                        <i class="bi bi-geo-alt me-1"></i> Q1: Polling Unit Results
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_tab === 'q2') ? 'active fw-bold' : '' ?>" href="lga_results.php">
                        <i class="bi bi-calculator me-1"></i> Q2: Total LGA Results
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_tab === 'q3') ? 'active fw-bold' : '' ?>" href="new_polling_unit.php">
                        <i class="bi bi-plus-circle me-1"></i> Q3: Record New Unit
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="py-4">
    <div class="container">
