<?php
// inc/subscription.php
// requires $pdo (PDO) OR $db (ezSQL) available in scope.

function ensure_minimum_one_month(PDO $pdo, int $user_id): bool {
    // fetch current expiry
    $stmt = $pdo->prepare("SELECT subscription_expires FROM k_users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $now = new DateTime('now');
    if (!$row || empty($row['subscription_expires'])) {
        $new = $now->modify('+30 days')->format('Y-m-d H:i:s');
    } else {
        $current = new DateTime($row['subscription_expires']);
        if ($current <= new DateTime('now')) {
            // expired — give 30 days from now
            $new = (new DateTime('now'))->modify('+30 days')->format('Y-m-d H:i:s');
        } else {
            // still active — extend by 30 days
            $new = $current->modify('+30 days')->format('Y-m-d H:i:s');
        }
    }
    $u = $pdo->prepare("UPDATE k_users SET subscription_expires = ? WHERE id = ?");
    return $u->execute([$new, $user_id]);
}

function add_months_to_subscription(PDO $pdo, int $user_id, int $months = 1): bool {
    // helper to add $months months (each month ~30 days or use modify("+{$months} months"))
    $stmt = $pdo->prepare("SELECT subscription_expires FROM k_users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['subscription_expires']) || new DateTime($row['subscription_expires']) <= new DateTime('now')) {
        $dt = (new DateTime('now'))->modify("+".(30*$months)." days");
    } else {
        $dt = (new DateTime($row['subscription_expires']))->modify("+".(30*$months)." days");
    }
    $u = $pdo->prepare("UPDATE k_users SET subscription_expires = ? WHERE id = ?");
    return $u->execute([$dt->format('Y-m-d H:i:s'), $user_id]);
}

function set_subscription_expiry(PDO $pdo, int $user_id, string $datetime): bool {
    // $datetime must be in valid date/time format
    $u = $pdo->prepare("UPDATE k_users SET subscription_expires = ? WHERE id = ?");
    return $u->execute([$datetime, $user_id]);
}

function subscription_remaining_days(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("SELECT subscription_expires FROM k_users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['subscription_expires'])) return 0;
    $now = new DateTime('now');
    $exp = new DateTime($row['subscription_expires']);
    if ($exp <= $now) return 0;
    $diff = $now->diff($exp);
    return (int)$diff->format('%a'); // days remaining
}
