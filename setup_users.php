<?php
// setup_users.php — Script to create accounts automatically
require_once 'app/config/db.php';

echo "Seeding users...\n";

// Passwords meet complexity: 6-50 chars, 1 upper, 1 lower, 1 digit, 1 special char
$users = [
    ['alice', 'alice@example.com', 'Alice1@pass', 1000.00, 'Alice Wonderland', 'Curiouser and curiouser.'],
    ['bob_b', 'bob@example.com', 'Bob2#secure', 500.00, 'Bob Builder', 'Can we fix it?'],
    ['charlie', 'charlie@example.com', 'Charlie3!x', 250.00, 'Charlie Chocolate', 'I love chocolate.'],
    ['eve_hacker', 'eve@example.com', 'Eve4$hack', 100.00, 'Eve Hacker', 'I like to listen.']
];

foreach ($users as $u) {
    try {
        $stmt = $pdo->prepare("INSERT INTO upyogkarta (naam, vipatra, gupt_sanket, shesh, poora_naam, parichay, satyapit) VALUES (?, ?, ?, ?, ?, ?, TRUE)");
        $hash = password_hash($u[2], PASSWORD_ARGON2ID);
        $stmt->execute([$u[0], $u[1], $hash, $u[3], $u[4], $u[5]]);
        echo "Created user: {$u[0]} (password: {$u[2]})\n";
    }
    catch (PDOException $e) {
        echo "Skipping {$u[0]} (already exists or error: " . $e->getMessage() . ")\n";
    }
}

echo "Done.\n";
?>