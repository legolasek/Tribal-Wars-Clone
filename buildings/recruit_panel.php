<?php
// This file is included from an AJAX endpoint and has access to variables like:
// $available_units, $village_units, $recruitment_queue, $village, $building_internal_name, $building_level
?>

<div class="recruit-panel">
    <h2>Recruit Units in <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $building_internal_name))) ?> (Level <?= $building_level ?>)</h2>

    <table class="recruit-table">
        <thead>
            <tr>
                <th>Unit</th>
                <th>Stats (A/D)</th>
                <th>Cost</th>
                <th>In Village</th>
                <th>Recruit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($available_units as $unit): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($unit['name_pl']) ?></strong>
                        <p><?= htmlspecialchars($unit['description_pl']) ?></p>
                    </td>
                    <td><?= $unit['attack'] ?> / <?= $unit['defense'] ?></td>
                    <td>
                        <span class="resource wood"><img src="../img/ds_graphic/wood.png" alt="Wood"> <?= $unit['wood_cost'] ?></span>
                        <span class="resource clay"><img src="../img/ds_graphic/stone.png" alt="Clay"> <?= $unit['clay_cost'] ?></span>
                        <span class="resource iron"><img src="../img/ds_graphic/iron.png" alt="Iron"> <?= $unit['iron_cost'] ?></span>
                    </td>
                    <td>
                        <?= $village_units[$unit['id']]['count'] ?? 0 ?>
                    </td>
                    <td>
                        <form class="recruit-form" data-unit-id="<?= $unit['id'] ?>" data-village-id="<?= $village['id'] ?>" data-building-name="<?= $building_internal_name ?>">
                            <input type="number" name="count" min="1" placeholder="0" class="recruit-input">
                            <button type="submit" class="btn btn-primary btn-small">Recruit</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="recruitment-queue">
        <h3>Recruitment Queue</h3>
        <?php if (!empty($recruitment_queue)): ?>
            <ul>
                <?php foreach ($recruitment_queue as $queue_item): ?>
                    <li>
                        <?= htmlspecialchars($queue_item['count']) ?> x <?= htmlspecialchars($queue_item['name_pl']) ?>
                        - Finishes in <span class="timer" data-finish-time="<?= $queue_item['finish_at'] ?>">...</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>The recruitment queue is empty.</p>
        <?php endif; ?>
    </div>
</div>