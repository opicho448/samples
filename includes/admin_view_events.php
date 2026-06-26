<section class="admin-view-panel">
    <div class="admin-widget-header">
        <div>
            <h2>Events</h2>
            <p>Manage upcoming and past events</p>
        </div>
        <a class="button" href="admin.php?view=events&amp;show_form=event">Create event</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Title</th><th>Date</th><th>Category</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="4">No events yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= escape($event['title']) ?></td>
                            <td><?= date('M j, Y', strtotime($event['event_date'])) ?></td>
                            <td><?= escape($event['category']) ?></td>
                            <td class="admin-action-group">
                                <a class="button small secondary" href="admin.php?view=events&amp;edit=<?= $event['id'] ?>">Edit</a>
                                <a class="button small btn-reject" href="admin.php?view=events&amp;delete=<?= $event['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($showEventForm): ?>
        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
            <h3><?= $editEvent ? 'Edit event' : 'Create event' ?></h3>
            <form method="post" action="admin.php?view=events" data-loading>
                <input type="hidden" name="event_id" value="<?= escape($editEvent['id'] ?? '0') ?>">
                <label>Title</label>
                <input type="text" name="title" value="<?= escape($_POST['title'] ?? $editEvent['title'] ?? '') ?>">
                <label>Description</label>
                <textarea name="description" rows="5"><?= escape($_POST['description'] ?? $editEvent['description'] ?? '') ?></textarea>
                <label>Date &amp; time</label>
                <input type="datetime-local" name="event_date" value="<?= escape($_POST['event_date'] ?? ($editEvent ? date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) : '')) ?>">
                <label>Venue</label>
                <input type="text" name="venue" value="<?= escape($_POST['venue'] ?? $editEvent['venue'] ?? '') ?>">
                <label>Organizer</label>
                <input type="text" name="organizer" value="<?= escape($_POST['organizer'] ?? $editEvent['organizer'] ?? '') ?>">
                <label>Organizer email</label>
                <input type="email" name="organizer_email" value="<?= escape($_POST['organizer_email'] ?? $editEvent['organizer_email'] ?? '') ?>">
                <label>Category</label>
                <input type="text" name="category" value="<?= escape($_POST['category'] ?? $editEvent['category'] ?? '') ?>">
                <label>Ticket options</label>
                <textarea name="ticket_options" rows="5" placeholder="One option per line: Name | Price | Quantity"><?= escape($_POST['ticket_options'] ?? $editEvent['ticket_options'] ?? '') ?></textarea>
                <div class="admin-action-group">
                    <input type="submit" value="Save event" class="button">
                    <a class="button secondary" href="admin.php?view=events">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
