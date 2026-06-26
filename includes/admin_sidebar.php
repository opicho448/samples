<?php
$navItems = [
    'overview' => ['label' => 'Overview', 'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-5H4v5Zm10-9h6V4h-6v7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>'],
    'approvals' => ['label' => 'Approvals', 'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 11l2 2 4-4M7.5 4.2A9 9 0 1 1 4.2 16.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>', 'badge' => $pendingCount ?? 0],
    'events' => ['label' => 'Events', 'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7 3v3M17 3v3M4 8h16M6 6h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'],
    'attendees' => ['label' => 'Attendees', 'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M16 11a3 3 0 1 0-6 0M4 20v-1a4 4 0 0 1 4-4h2.5M20 20v-1a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'],
    'venues' => ['label' => 'Venues', 'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M6 21V8l6-4 6 4v13M10 21v-6h4v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
];
?>
<aside class="admin-sidebar">
    <p class="admin-sidebar-brand">Admin Console</p>
    <nav class="admin-nav" aria-label="Admin navigation">
        <?php foreach ($navItems as $viewKey => $item): ?>
            <a class="admin-nav-link<?= ($currentView ?? '') === $viewKey ? ' is-active' : '' ?>" href="admin.php?view=<?= escape($viewKey) ?>">
                <?= $item['icon'] ?>
                <span><?= escape($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                    <span class="admin-nav-badge"><?= (int)$item['badge'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a class="admin-nav-link<?= ($currentView ?? '') === 'message' ? ' is-active' : '' ?>" href="admin.php?view=message">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Broadcast</span>
        </a>
        <a class="admin-nav-link<?= ($currentView ?? '') === 'logs' ? ' is-active' : '' ?>" href="admin.php?view=logs">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span>System logs</span>
        </a>
    </nav>
</aside>
