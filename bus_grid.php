<?php foreach ($busData as $vhcId => $data): ?>
    <div class="card p-4 rounded shadow" data-bus-id="<?= htmlspecialchars($vhcId) ?>">
        <h3 class="text-xl font-bold mb-2">ID Vozidla: <?= htmlspecialchars($vhcId) ?></h3>
        <p class="text-sm text-gray-500 mb-2">
            Přidáno uživatelem: <?= htmlspecialchars($data['added_by'] ?? 'system') ?>
            <?php if (isset($data['added_at'])): ?>
                <br>
                <span class="text-xs">
                    <?= htmlspecialchars($data['added_at']) ?>
                </span>
            <?php endif; ?>
        </p>
        <?php if (!empty($data['image'])): ?>
            <img src="<?= htmlspecialchars($data['image']) ?>" alt="Bus Image" class="mb-2 max-w-full h-auto">
        <?php else: ?>
            <p class="mb-2">Obrázek není k dispozici</p>
        <?php endif; ?>
        <?php if (!empty($data['url'])): ?>
            <a href="<?= htmlspecialchars($data['url']) ?>" target="_blank" class="text-blue-500 hover:text-blue-700">Zobrazit na Seznam-autobusu.cz</a>
        <?php else: ?>
            <p>URL Seznam-autobusu.cz není k dispozici</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>