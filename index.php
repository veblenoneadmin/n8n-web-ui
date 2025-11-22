<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<h2>Database connection error</h2><p>config.php must create a PDO instance named $pdo.</p>');
}

// ---------------------------
// AJAX: return booked personnel IDs for a date
// ---------------------------
if (isset($_GET['check_booked']) && $_GET['check_booked']) {
    $date = $_GET['date'] ?? null;
    header('Content-Type: application/json; charset=utf-8');
    if (!$date) {
        echo json_encode([]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
        $stmt->execute([$date]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(array_map('strval', $ids));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// ---------------------------
// Helpers
// ---------------------------
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $q->execute([$column]);
        return (bool)$q->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function find_split_table(PDO $pdo, array $candidates) {
    foreach ($candidates as $t) {
        $r = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        if ($r) return $t;
    }
    return null;
}

// ---------------------------
// Load lists
// ---------------------------
$message = '';
$products = [];
$personnel = [];
$ducted_installations = [];
$split_installations = [];
$equipment = [];

try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $message = "❌ Failed to load products: ".$e->getMessage(); }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $message = "❌ Failed to load personnel: ".$e->getMessage(); }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name, model_name_indoor, model_name_outdoor, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations = []; $message = "❌ Failed to load ducted installations: ".$e->getMessage(); }
try { $equipment = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment = []; }

// Split installation table
$split_table_candidates = ['split_system_installation', 'split_installations', 'split_systems', 'split_installation'];
$found = find_split_table($pdo, $split_table_candidates);
if ($found) {
    try {
        $split_installations = $pdo->query("SELECT id, item_name, unit_price AS price FROM `$found` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){ $split_installations = []; $message = "❌ Failed to load split installations: ".$e->getMessage(); }
}

// Appointment date
$selected_date = $_POST['appointment_date'] ?? $_GET['date'] ?? null;
$booked_personnel_ids = [];
if ($selected_date) {
    try {
        $q = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
        $q->execute([$selected_date]);
        $booked_personnel_ids = $q->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e){ $booked_personnel_ids = []; }
}

// ---------------------------
// Handle POST - create order
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $generateOrderNumber = fn() => 'ORD-'.date('YmdHis').'-'.rand(100,999);

    $customer_name     = trim($_POST['customer_name'] ?? '');
    $customer_email    = trim($_POST['customer_email'] ?? '');
    $contact_number    = trim($_POST['contact_number'] ?? '');
    $appointment_date  = trim($_POST['appointment_date'] ?? '');
    $quantities        = $_POST['quantity'] ?? [];
    $split_quantities  = $_POST['split'] ?? [];
    $ducted_inputs     = $_POST['ducted'] ?? [];
    $personnel_inputs  = $_POST['personnel_selected'] ?? [];

    if ($customer_name === '') {
        $message = '⚠️ Please enter a customer name.';
    } else {
        try {
            $pdo->beginTransaction();

            // Determine order columns
            $hasCustomerEmail   = column_exists($pdo, 'orders', 'customer_email');
            $hasContactNumber   = column_exists($pdo, 'orders', 'contact_number');
            $hasAppointmentDate = column_exists($pdo, 'orders', 'appointment_date');

            $cols = ['order_number','customer_name'];
            $placeholders = ['?','?'];
            $values = [];

            $order_number = $generateOrderNumber();
            $values[] = $order_number;
            $values[] = $customer_name;

            if ($hasCustomerEmail) { $cols[]='customer_email'; $placeholders[]='?'; $values[] = $customer_email?:null; }
            if ($hasContactNumber) { $cols[]='contact_number'; $placeholders[]='?'; $values[] = $contact_number?:null; }
            if ($hasAppointmentDate) { $cols[]='appointment_date'; $placeholders[]='?'; $values[] = $appointment_date?:null; }

            $cols[]='total_amount'; $placeholders[]='?'; $values[] = 0;

            $sql = "INSERT INTO orders (".implode(',', $cols).") VALUES (".implode(',', $placeholders).")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $order_id = $pdo->lastInsertId();

            // INSERT order_items (without line_total!)
            $insertStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $total = 0.0;

            // Products
            foreach ($quantities as $pid=>$qtyRaw) {
                $pidInt = intval($pid); $qty = max(0,intval($qtyRaw));
                if ($qty>0) {
                    $prod = array_filter($products, fn($p)=>$p['id']==$pidInt);
                    if ($prod) {
                        $prod = array_values($prod)[0];
                        $price = floatval($prod['price']);
                        $insertStmt->execute([$order_id,'product',$pidInt,null,$qty,$price]);
                        $total += $qty*$price;
                    }
                }
            }

            // Split installations
            foreach ($split_quantities as $sid=>$sdata) {
                $sidInt = intval($sid);
                $qty = max(0,intval($sdata['qty']??0));
                if ($qty>0) {
                    $row = array_values(array_filter($split_installations, fn($s)=>$s['id']==$sidInt))[0]??null;
                    if ($row) { $price=floatval($row['price']); $insertStmt->execute([$order_id,'installation',$sidInt,null,$qty,$price]); $total+=$qty*$price; }
                }
            }

            // Personnel
            foreach ($personnel_inputs as $pid) {
                $pidInt = intval($pid);
                $pers = array_values(array_filter($personnel, fn($p)=>$p['id']==$pidInt))[0]??null;
                if ($pers) { $price=floatval($pers['rate']); $insertStmt->execute([$order_id,'personnel',$pidInt,null,1,$price]); $total+=$price;

                    if ($appointment_date) {
                        try {
                            $tbl = $pdo->query("SHOW TABLES LIKE 'personnel_bookings'")->fetchColumn();
                            if ($tbl) {
                                $chk = $pdo->prepare("SELECT COUNT(*) FROM personnel_bookings WHERE personnel_id=? AND booked_date=?");
                                $chk->execute([$pidInt,$appointment_date]);
                                if ((int)$chk->fetchColumn()===0) {
                                    $pdo->prepare("INSERT INTO personnel_bookings (personnel_id, booked_date) VALUES (?,?)")->execute([$pidInt,$appointment_date]);
                                }
                            }
                        } catch(Exception $ex){}
                    }
                }
            }

            // Ducted installations
            foreach ($ducted_inputs as $did=>$d) {
                $didInt=intval($did); $qty=max(0,intval($d['qty']??0)); $type=$d['installation_type']??'';
                if ($qty>0 && $type!=='') {
                    $row=array_values(array_filter($ducted_installations, fn($r)=>$r['id']==$didInt))[0]??null;
                    if ($row) { $price=floatval($row['total_cost']); $insertStmt->execute([$order_id,'installation',$didInt,$type,$qty,$price]); $total+=$qty*$price; }
                }
            }

            $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?")->execute([round($total,2),$order_id]);

            $pdo->commit();

            header("Location: orders?order_id=".urlencode($order_id));
            exit;

        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Failed to create order: ".$e->getMessage();
        }
    }
}

// ---------------------------
// Render page
// ---------------------------
ob_start();
// ... Your existing HTML form + JS remains the same ...
$content = ob_get_clean();
renderLayout('Create Order',$content);
?>
