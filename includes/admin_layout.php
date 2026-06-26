<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Hotel Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="admin-dashboard.css">
</head>
<body class="page-admin">
<?php include __DIR__ . '/../header.php'; ?>
<div class="admin-shell">
    <?php include __DIR__ . '/admin_sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1><?= escape($pageMeta['title']) ?></h1>
                <p><?= escape($pageMeta['subtitle']) ?></p>
            </div>
            <div class="admin-topbar-actions">
                <?php if ($pendingCount > 0): ?>
                    <a class="button" href="#" data-open-approvals-modal>Review <?= (int)$pendingCount ?> pending</a>
                <?php endif; ?>
                <a class="button secondary" href="admin.php?view=message">Broadcast</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="notice"><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($approvalMessage): ?>
            <div class="notice"><?= escape($approvalMessage) ?></div>
        <?php endif; ?>
        <?php if ($userMessage): ?>
            <div class="notice"><?= escape($userMessage) ?></div>
        <?php endif; ?>

        <?php
        if ($currentView === 'overview') {
            include __DIR__ . '/admin_view_overview.php';
        } elseif ($currentView === 'events') {
            include __DIR__ . '/admin_view_events.php';
        } elseif ($currentView === 'attendees') {
            include __DIR__ . '/admin_view_attendees.php';
        } elseif ($currentView === 'venues') {
            include __DIR__ . '/admin_view_venues.php';
        } elseif ($currentView === 'approvals') {
            include __DIR__ . '/admin_view_approvals.php';
        } elseif ($currentView === 'message') {
            include __DIR__ . '/admin_view_message.php';
        } elseif ($currentView === 'logs') {
            include __DIR__ . '/admin_view_logs.php';
        }
        ?>
    </main>
</div>

<div class="admin-modal-backdrop" id="approvalsModal" aria-hidden="true">
    <div class="admin-modal" role="dialog" aria-labelledby="approvalsModalTitle">
        <div class="admin-modal-header">
            <h2 id="approvalsModalTitle">Pending Approvals</h2>
            <button type="button" class="admin-modal-close" data-close-approvals-modal aria-label="Close">&times;</button>
        </div>
        <div class="admin-modal-body">
            <?php if (empty($pendingApprovals)): ?>
                <div class="admin-status-banner ok">
                    <div class="admin-status-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 12.5 11 14.5 15.5 9.5M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <strong>All systems normal</strong>
                        <span>No registrations awaiting approval.</span>
                    </div>
                </div>
            <?php else: ?>
                <?= adminPendingTable($pendingApprovals, 'overview', false) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="admin-dashboard.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
