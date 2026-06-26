<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Logged Out</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-login">
<header>
    <div class="container">
        <h1>See you soon!</h1>
    </div>
</header>
<main class="container">
    <section class="panel">
        <h2>It was nice having you on board.</h2>
        <p>Your session has ended successfully.</p>
        <a class="button" href="dashboard.php">Return to Dashboard</a>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
