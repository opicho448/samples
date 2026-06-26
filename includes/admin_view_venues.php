<section class="admin-view-panel">
    <div class="admin-widget-header">
        <div>
            <h2>Venues</h2>
            <p>Manage venue inventory and capacity</p>
        </div>
        <a class="button" href="admin.php?view=venues&amp;show_form=venue">Add venue</a>
    </div>

    <?php if ($venueMessage): ?><div class="notice"><?= escape($venueMessage) ?></div><?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>Location</th><th>Capacity</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($venuesList)): ?>
                    <tr><td colspan="5">No venues yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($venuesList as $v): ?>
                        <tr>
                            <td><?= escape($v['name']) ?></td>
                            <td><?= escape($v['location']) ?></td>
                            <td><?= escape($v['capacity']) ?></td>
                            <td><?= escape($v['description']) ?></td>
                            <td class="admin-action-group">
                                <a class="button small secondary" href="admin.php?view=venues&amp;edit_venue=<?= $v['id'] ?>">Edit</a>
                                <form method="post" action="admin.php?view=venues" data-loading>
                                    <input type="hidden" name="venue_action" value="delete">
                                    <input type="hidden" name="venue_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="button small btn-reject" onclick="return confirm('Remove venue?')">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($showVenueForm): ?>
        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
            <h3><?= $venueEdit ? 'Edit venue' : 'Add venue' ?></h3>
            <form method="post" action="admin.php?view=venues" data-loading>
                <input type="hidden" name="venue_action" value="<?= $venueEdit ? 'edit' : 'add' ?>">
                <?php if ($venueEdit): ?><input type="hidden" name="venue_id" value="<?= escape($venueEdit['id']) ?>"><?php endif; ?>
                <label>Venue name</label>
                <input type="text" name="venue_name" value="<?= escape($_POST['venue_name'] ?? $venueEdit['name'] ?? '') ?>">
                <label>Location</label>
                <input type="text" name="venue_location" value="<?= escape($_POST['venue_location'] ?? $venueEdit['location'] ?? '') ?>">
                <label>Capacity</label>
                <input type="number" name="venue_capacity" value="<?= escape($_POST['venue_capacity'] ?? $venueEdit['capacity'] ?? '') ?>">
                <label>Description</label>
                <textarea name="venue_description" rows="3"><?= escape($_POST['venue_description'] ?? $venueEdit['description'] ?? '') ?></textarea>
                <div class="admin-action-group">
                    <input type="submit" value="<?= $venueEdit ? 'Update venue' : 'Add venue' ?>" class="button">
                    <a class="button secondary" href="admin.php?view=venues">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
