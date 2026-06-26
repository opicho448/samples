<?php
require_once __DIR__ . '/helpers.php';
$user = currentUser();
$unreadNotifications = 0;
$unreadMessages = 0;
$theme = 'light';
if ($user) {
    try {
        ensureMessagesSchema();
        ensureUserThemeSchema();
    } catch (PDOException $e) {
        // If the schema cannot be fixed automatically, continue.
    }
    if (!empty($_SESSION['theme'])) {
        $theme = $_SESSION['theme'];
    } elseif (!empty($user['theme'])) {
        $theme = $user['theme'];
        $_SESSION['theme'] = $theme;
    }
}
if (empty($theme)) {
    $theme = 'light';
}
    $unreadNotifications = 0;
    $unreadMessages = 0;
    if ($user) {
        try {
            $c = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
            $c->execute(['uid' => $user['id']]);
            $unreadNotifications = (int)$c->fetchColumn();
        } catch (PDOException $e) {
            // Older schema may not have is_read — fall back to 0 without failing
            $unreadNotifications = 0;
        }
        try {
            $m = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE to_user_id = :uid AND is_read = 0');
            $m->execute(['uid' => $user['id']]);
            $unreadMessages = (int)$m->fetchColumn();
        } catch (PDOException $e) {
            // Messages table or is_read column may not exist yet
            $unreadMessages = 0;
        }
    }

?>
<header style="opacity: 0.8; ">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
        <div style="display:flex;align-items:center;gap:1rem; border-radius: 50%; overflow: hidden;">
            <a href="dashboard.php" style="color:inherit;text-decoration:none;font-weight:700; background:#fff;"><img src="images/hotel_logo.png" alt="premier hotel logo" width="100px" height="90px"></a>
        </div>
        <nav style="display:flex;align-items:center;gap:1rem; ">
            <?php
            $current = basename($_SERVER['PHP_SELF'] ?? '');
            $secureAccessPages = ['login.php', 'register_user.php', 'forgot_password.php', 'reset_password.php'];
            $isSecureAccess = in_array($current, $secureAccessPages, true);
            // role-aware dashboard link: admin -> admin.php, organizer -> organizer.php, others -> my_events.php
            $dashboardLink = 'dashboard.php';
            if (!empty($user) && !empty($user['role'])) {
                if ($user['role'] === 'admin') {
                    $dashboardLink = 'admin.php';
                } elseif ($user['role'] === 'organizer') {
                    $dashboardLink = 'organizer.php';
                } else {
                    $dashboardLink = 'my_events.php';
                }
            }
            if ($isSecureAccess) {
                if ($user) {
                    echo '<a href="' . escape($dashboardLink) . '">Dashboard</a>';
                }
            } else {
            ?>
                <a href="<?= escape($dashboardLink) ?>">Dashboard</a>
                <a href="index.php">Events</a>
                <a href="venues.php">Venues</a>
                <?php if ($user) { ?>
                    <a href="notifications.php" style="position:relative;" aria-label="Notifications" title="Notifications">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8a6 6 0 0 0-12 0v4.5c0 1.7-1.2 3.2-2.8 3.7l-.2.1V18h18v-1.7l-.2-.1c-1.6-.5-2.8-2-2.8-3.7V8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 21h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M12 17.5a1.5 1.5 0 0 0 1.5-1.5h-3A1.5 1.5 0 0 0 12 17.5Z" fill="currentColor"/>
                    </svg>
                    <?php if ($unreadNotifications > 0) { ?><span style="position:absolute;top:-6px;right:-8px;background:#e3342f;color:#fff;border-radius:999px;padding:2px 6px;font-size:12px;"><?= $unreadNotifications ?></span><?php } ?>
                </a>
                <a href="messages.php" style="position:relative;" aria-label="Messages" title="Messages">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <?php if ($unreadMessages > 0) { ?><span style="position:absolute;top:-6px;right:-8px;background:#2b8a3e;color:#fff;border-radius:999px;padding:2px 6px;font-size:12px;"><?= $unreadMessages ?></span><?php } ?>
                </a>
                    <details class="profile-menu" id="profileDropdown">
                        <summary id="profileDropdownToggle" aria-haspopup="true" aria-expanded="false" aria-label="Profile menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 21v-1a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> Profile
                        </summary>
                        <div class="profile-dropdown">
                            <div class="profile-dropdown-panel">
                                <div class="profile-summary" id="profileSummary">
                                    <h3>My profile</h3>
                                    <p><strong>Name:</strong> <?= escape($user['name'] ?? '') ?></p>
                                    <p><strong>Email:</strong> <?= escape($user['email'] ?? '') ?></p>
                                    <p><strong>Phone:</strong> <?= escape($user['phone'] ?? '') ?></p>
                                    <p><strong>Role:</strong> <?= escape($user['role'] ?? '') ?></p>
                                    <div class="profile-actions">
                                        <button type="button" class="button secondary" id="editProfileButton">Edit</button>
                                        <a class="button secondary" href="logout.php">Logout</a>
                                    </div>
                                </div>
                                <form class="profile-edit-form hidden" id="profileEditForm" action="profile.php" method="post">
                                    <h3>Edit profile</h3>
                                    <input type="hidden" name="redirect" id="profileRedirect" value="">
                                    <label>Name
                                        <input type="text" name="name" value="<?= escape($user['name'] ?? '') ?>" required>
                                    </label>
                                    <label>Email
                                        <input type="email" name="email" value="<?= escape($user['email'] ?? '') ?>" required>
                                    </label>
                                    <label>Phone
                                        <input type="text" name="phone" value="<?= escape($user['phone'] ?? '') ?>">
                                    </label>
                                    <div class="profile-edit-actions">
                                        <button type="submit" class="button">Save</button>
                                        <button type="button" class="button secondary" id="cancelProfileEdit">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </details>
                <?php } else { ?>
                    <a href="login.php">Login</a>
                    <a href="register_user.php">Sign up</a>
                <?php } ?>
            <?php } ?>
            <form method="post" action="set_theme.php" class="theme-select-form">
                <label for="themeSelect">Theme</label>
                <select id="themeSelect" name="theme" onchange="this.form.submit()">
                    <option value="light"<?= $theme === 'light' ? ' selected' : '' ?>>Light</option>
                    <option value="dark"<?= $theme === 'dark' ? ' selected' : '' ?>>Dark</option>
                    <option value="blue"<?= $theme === 'blue' ? ' selected' : '' ?>>Blue</option>
                </select>
                <input type="hidden" name="redirect" value="<?= escape($_SERVER['REQUEST_URI'] ?? '/') ?>">
            </form>
        </nav>
        <?php if ($user): ?>
            <script>
                (function() {
                    const details = document.getElementById('profileDropdown');
                    const toggle = document.getElementById('profileDropdownToggle');
                    const editButton = document.getElementById('editProfileButton');
                    const summary = document.getElementById('profileSummary');
                    const form = document.getElementById('profileEditForm');
                    const cancel = document.getElementById('cancelProfileEdit');
                    const redirect = document.getElementById('profileRedirect');

                    if (redirect) {
                        redirect.value = window.location.pathname + window.location.search;
                    }

                    if (details && toggle) {
                        details.addEventListener('toggle', function() {
                            toggle.setAttribute('aria-expanded', details.hasAttribute('open') ? 'true' : 'false');
                        });
                    }

                    document.addEventListener('click', function(event) {
                        if (!details || !details.hasAttribute('open')) {
                            return;
                        }
                        if (!details.contains(event.target)) {
                            details.removeAttribute('open');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    });

                    if (editButton && form && cancel) {
                        editButton.addEventListener('click', function() {
                            summary.classList.add('hidden');
                            form.classList.remove('hidden');
                        });
                        cancel.addEventListener('click', function() {
                            form.classList.add('hidden');
                            summary.classList.remove('hidden');
                        });
                    }
                })();
            </script>
        <?php endif; ?>
        <script>document.body.classList.add('theme-<?= escape($theme) ?>');</script>
    </div>
</header>
