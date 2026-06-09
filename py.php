<?php
// fix_payments_table_v2.php — safe repair utility
require_once "config.php"; // adjust path if needed
global $db;
echo "<pre>=== Payments Table Safe Fix v2 ===\n\n";

try {
    // 1) table exists?
    $exists = (int)$db->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='k_payments'");
    if (!$exists) { echo "Table k_payments not found.\n"; exit; }

    // 2) show create table
    $create = $db->get_var("SHOW CREATE TABLE k_payments");
    echo "SHOW CREATE TABLE: \n";
    var_export($create);
    echo "\n\n";

    // 3) remove id=0 rows
    $db->query("DELETE FROM k_payments WHERE id = 0");
    echo "Removed id=0 rows (if existed).\n";

    // 4) find duplicates
    $dupes = $db->get_results("SELECT id, COUNT(*) c FROM k_payments GROUP BY id HAVING c>1");
    if ($dupes) {
        echo "Duplicates found:\n";
        foreach($dupes as $d) {
            echo " - id={$d->id} count={$d->c}\n";
        }
        echo "\nPlease resolve duplicates manually (or ask me) and re-run.\n";
        exit;
    } else {
        echo "No duplicate ids found.\n";
    }

    // 5) check if PRIMARY exists
    $pk = $db->get_results("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='k_payments' AND CONSTRAINT_NAME='PRIMARY'");
    if ($pk && count($pk)>0) {
        echo "PRIMARY key already exists on column(s): ";
        foreach($pk as $col) echo $col->COLUMN_NAME . " ";
        echo "\n";
    } else {
        echo "No PRIMARY key found. Attempting to add PRIMARY on id...\n";
        // ensure id column exists
        $has_id = (int)$db->get_var("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='k_payments' AND column_name='id'");
        if (!$has_id) { echo "Column 'id' not found. Cannot add PRIMARY.\n"; exit; }

        // make sure id is integer and NOT NULL
        $db->query("ALTER TABLE k_payments MODIFY id INT UNSIGNED NOT NULL");
        echo "Modified id to INT UNSIGNED NOT NULL.\n";

        // add primary
        $db->query("ALTER TABLE k_payments ADD PRIMARY KEY (id)");
        echo "Added PRIMARY key on id.\n";

        // make it AUTO_INCREMENT
        $db->query("ALTER TABLE k_payments MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
        echo "Set id to AUTO_INCREMENT.\n";

        // reset auto_increment
        $next = (int)$db->get_var("SELECT COALESCE(MAX(id),0)+1 FROM k_payments");
        if ($next < 1) $next = 1;
        $db->query("ALTER TABLE k_payments AUTO_INCREMENT = {$next}");
        echo "AUTO_INCREMENT set to {$next}.\n";
    }

    echo "\n✅ Done. If no errors shown above, table should be fixed.\n";
} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
