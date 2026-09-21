<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = database();
$existingUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($existingUsers > 0) {
    fwrite(STDERR, "Seed data was not created because the users table is not empty.\n");
    exit(1);
}

$accounts = [
    ['A000001', 'System Administrator', 'admin', 'AdminDemo2026!'],
    ['S000001', 'Campus Staff', 'staff', 'StaffDemo2026!'],
    ['U000001', 'Amina Student', 'student', 'StudentDemo2026!'],
    ['U000002', 'Boon Student', 'student', 'StudentDemo2026!'],
    ['U000003', 'Chai Student', 'student', 'StudentDemo2026!'],
    ['U000004', 'Dao Student', 'student', 'StudentDemo2026!'],
    ['U000005', 'Eka Student', 'student', 'StudentDemo2026!'],
];

$pdo->beginTransaction();
try {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (id_number, full_name, role, password_hash, created_by) VALUES (?, ?, ?, ?, ?)'
    );

    $insertUser->execute([$accounts[0][0], $accounts[0][1], $accounts[0][2], password_hash($accounts[0][3], PASSWORD_DEFAULT), null]);
    $adminId = (int) $pdo->lastInsertId();

    foreach (array_slice($accounts, 1) as [$idNumber, $name, $role, $password]) {
        $insertUser->execute([$idNumber, $name, $role, password_hash($password, PASSWORD_DEFAULT), $adminId]);
    }

    $staffId = (int) $pdo->query("SELECT id FROM users WHERE id_number = 'S000001'")->fetchColumn();
    $insertItem = $pdo->prepare(
        'INSERT INTO found_items (item_name, category, colour, brand, public_description, private_details, found_location, location_detail, found_datetime, received_datetime, received_by, storage_location)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)'
    );
    $items = [
        ['Black backpack', 'Bag or Luggage', 'Black', null, 'Black backpack found near the library entrance.', 'Small university badge inside front pocket.', 'Library', 'Main entrance', '2026-09-19 10:30:00', 'Locked cabinet A'],
        ['Blue water bottle', 'Bottle or Container', 'Blue', null, 'Blue reusable water bottle.', 'Initials B.S. beneath the base.', 'Sports Complex', 'Indoor court', '2026-09-20 16:00:00', 'Shelf B'],
    ];
    foreach ($items as $item) {
        $storageLocation = array_pop($item);
        $insertItem->execute([...$item, $staffId, $storageLocation]);
        $itemId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE found_items SET reference_number = ? WHERE id = ?')
            ->execute([sprintf('FI-%06d', $itemId), $itemId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

echo "Seeded local demo accounts and sample found items.\n";
foreach ($accounts as [$idNumber, , $role, $password]) {
    echo sprintf("%s (%s): %s\n", $idNumber, $role, $password);
}
