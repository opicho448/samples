<section class="admin-view-panel">
    <div class="admin-widget-header">
        <div>
            <h2>Attendee list</h2>
            <p>Search, filter, and export registrations</p>
        </div>
        <a class="button secondary" href="export_attendees.php">Export CSV</a>
    </div>

    <form method="get" action="admin.php" class="admin-filters">
        <input type="hidden" name="view" value="attendees">
        <div>
            <label for="search">Search</label>
            <input type="text" id="search" name="search" placeholder="Name, email, or event" value="<?= escape($attendeeSearch) ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option value="Pending"<?= $attendeeStatus === 'Pending' ? ' selected' : '' ?>>Pending</option>
                <option value="Confirmed"<?= $attendeeStatus === 'Confirmed' ? ' selected' : '' ?>>Confirmed</option>
                <option value="Rejected"<?= $attendeeStatus === 'Rejected' ? ' selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div>
            <label for="date_from">From</label>
            <input type="date" id="date_from" name="date_from" value="<?= escape($attendeeDateFrom) ?>">
        </div>
        <div>
            <label for="date_to">To</label>
            <input type="date" id="date_to" name="date_to" value="<?= escape($attendeeDateTo) ?>">
        </div>
        <div class="admin-filter-actions">
            <button type="submit" class="button">Apply</button>
            <a class="button secondary" href="admin.php?view=attendees">Reset</a>
        </div>
    </form>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Event</th><th>Name</th><th>Email</th><th>Ticket</th><th>Ticket #</th><th>Status</th><th>Registered</th></tr>
            </thead>
            <tbody>
                <?php if (empty($attendees)): ?>
                    <tr><td colspan="7">No attendees match your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendees as $attendee): ?>
                        <?php $status = $attendee['registration_status'] ?? $attendee['payment_status']; ?>
                        <tr>
                            <td><?= escape($attendee['event_title']) ?></td>
                            <td><?= escape($attendee['name']) ?></td>
                            <td><?= escape($attendee['email']) ?></td>
                            <td><?= escape($attendee['ticket_type']) ?></td>
                            <td><?= escape($attendee['ticket_code'] ?: '—') ?></td>
                            <td><?= adminStatusPill($status) ?></td>
                            <td><?= escape($attendee['registered_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        <div class="admin-pagination-meta">
            Showing <?= $attendeeTotal === 0 ? 0 : ($attendeeOffset + 1) ?>–<?= min($attendeeOffset + $attendeePerPage, $attendeeTotal) ?> of <?= number_format($attendeeTotal) ?>
        </div>
        <div class="admin-action-group">
            <?php if ($attendeePage > 1): ?>
                <a class="button secondary small" href="admin.php?<?= escape(http_build_query(array_merge($_GET, ['page' => $attendeePage - 1]))) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($attendeePage < $attendeeTotalPages): ?>
                <a class="button secondary small" href="admin.php?<?= escape(http_build_query(array_merge($_GET, ['page' => $attendeePage + 1]))) ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</section>
