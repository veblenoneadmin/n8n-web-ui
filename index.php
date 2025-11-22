<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<h2>Database connection error</h2><p>config.php must create a PDO instance named $pdo.</p>');
}

// ---------------------------
// Helper Functions
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
// Load Lists
// ---------------------------
$message = '';
$products = $personnel = $ducted_installations = $split_installations = $equipment = [];

try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){$message = $e->getMessage();}
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){$message = $e->getMessage();}
try { $ducted_installations = $pdo->query("SELECT id, equipment_name, model_name_indoor, model_name_outdoor, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){$ducted_installations=[];}
try { $equipment = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){$equipment=[];}

$split_table_candidates = ['split_system_installation','split_installations','split_systems','split_installation'];
$found_split = find_split_table($pdo,$split_table_candidates);
if($found_split){
    try {
        $split_installations = $pdo->query("SELECT id, item_name, unit_price FROM `$found_split` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){$split_installations=[];}
}

// ---------------------------
// Booked personnel for selected date
// ---------------------------
$selected_date = $_POST['appointment_date'] ?? $_GET['date'] ?? null;
$booked_personnel_ids = [];
if($selected_date){
    try {
        $stmt = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
        $stmt->execute([$selected_date]);
        $booked_personnel_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e){$booked_personnel_ids = [];}
}

// ---------------------------
// Handle POST - Create Order
// ---------------------------
if($_SERVER['REQUEST_METHOD']==='POST'){

    $generateOrderNumber = fn()=> 'ORD-'.date('YmdHis').'-'.rand(100,999);

    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_email   = trim($_POST['customer_email'] ?? '');
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $product_quantities = $_POST['quantity'] ?? [];
    $split_quantities   = $_POST['split'] ?? [];
    $ducted_inputs      = $_POST['ducted'] ?? [];
    $personnel_hours    = $_POST['personnel_hours'] ?? [];
    $equipment_qty      = $_POST['equipment_qty'] ?? [];

    if($customer_name===''){
        $message='⚠️ Please enter customer name.';
    } else {
        try{
            $pdo->beginTransaction();

            // Insert order
            $cols=['order_number','customer_name']; $placeholders=['?','?']; $values=[$generateOrderNumber(),$customer_name];
            if(column_exists($pdo,'orders','customer_email')){$cols[]='customer_email'; $placeholders[]='?'; $values[]=$customer_email?:null;}
            if(column_exists($pdo,'orders','contact_number')){$cols[]='contact_number'; $placeholders[]='?'; $values[]=$contact_number?:null;}
            if(column_exists($pdo,'orders','appointment_date')){$cols[]='appointment_date'; $placeholders[]='?'; $values[]=$appointment_date?:null;}
            $cols[]='total_amount'; $placeholders[]='?'; $values[]=0;

            $stmt = $pdo->prepare("INSERT INTO orders (".implode(',',$cols).") VALUES (".implode(',',$placeholders).")");
            $stmt->execute($values);
            $order_id = $pdo->lastInsertId();

            // Prepare order_items insert
            $insertStmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,installation_type,qty,price,line_total) VALUES (?,?,?,?,?,?,?)");
            $total = 0.0;

            // --- PRODUCTS ---
            foreach($product_quantities as $pid=>$qtyRaw){
                $pidInt=intval($pid); $qty=max(0,intval($qtyRaw));
                if($qty>0){
                    $prod=array_filter($products, fn($p)=>$p['id']==$pidInt);
                    if($prod){$prod=array_values($prod)[0]; $price=floatval($prod['price']); $subtotal=$price*$qty;
                        $insertStmt->execute([$order_id,'product',$pidInt,null,$qty,$price,$subtotal]); $total+=$subtotal;
                    }
                }
            }

            // --- SPLIT INSTALLATIONS ---
            foreach($split_quantities as $sid=>$sdata){
                $sidInt=intval($sid); $qty=max(0,intval($sdata['qty']??0));
                if($qty>0){
                    $row=array_filter($split_installations, fn($s)=>$s['id']==$sidInt);
                    if($row){$row=array_values($row)[0]; $price=floatval($row['unit_price']); $subtotal=$price*$qty;
                        $insertStmt->execute([$order_id,'installation',$sidInt,null,$qty,$price,$subtotal]); $total+=$subtotal;
                    }
                }
            }

            // --- DUCTED INSTALLATIONS ---
            foreach($ducted_inputs as $did=>$d){
                $didInt=intval($did); $qty=max(0,intval($d['qty']??0)); $type=$d['installation_type']??'';
                if($qty>0 && $type!==''){
                    $row=array_filter($ducted_installations, fn($r)=>$r['id']==$didInt);
                    if($row){$row=array_values($row)[0]; $price=floatval($row['total_cost']); $subtotal=$price*$qty;
                        $insertStmt->execute([$order_id,'installation',$didInt,$type,$qty,$price,$subtotal]); $total+=$subtotal;
                    }
                }
            }

            // --- PERSONNEL ---
            foreach($personnel_hours as $pid=>$hoursRaw){
                $pidInt=intval($pid); $hours=max(0,floatval($hoursRaw));
                if($hours>0){
                    $pers=array_filter($personnel, fn($p)=>$p['id']==$pidInt);
                    if($pers){$pers=array_values($pers)[0]; $rate=floatval($pers['rate']); $subtotal=$rate*$hours;
                        $insertStmt->execute([$order_id,'personnel',$pidInt,null,$hours,$rate,$subtotal]); $total+=$subtotal;
                        // Book personnel
                        if($appointment_date && column_exists($pdo,'personnel_bookings','personnel_id')){
                            $chk=$pdo->prepare("SELECT COUNT(*) FROM personnel_bookings WHERE personnel_id=? AND booked_date=?");
                            $chk->execute([$pidInt,$appointment_date]);
                            if((int)$chk->fetchColumn()===0){$pdo->prepare("INSERT INTO personnel_bookings (personnel_id,booked_date) VALUES (?,?)")->execute([$pidInt,$appointment_date]);}
                        }
                    }
                }
            }

            // --- EQUIPMENT ---
            foreach($equipment_qty as $eid=>$qtyRaw){
                $eidInt=intval($eid); $qty=max(0,intval($qtyRaw));
                if($qty>0){
                    $equip=array_filter($equipment, fn($e)=>$e['id']==$eidInt);
                    if($equip){$equip=array_values($equip)[0]; $rate=floatval($equip['rate']); $subtotal=$rate*$qty;
                        $insertStmt->execute([$order_id,'equipment',$eidInt,null,$qty,$rate,$subtotal]); $total+=$subtotal;
                    }
                }
            }

            // --- OTHER EXPENSES ---
            $other_names = $_POST['other_expense']['name'] ?? [];
            $other_amount = $_POST['other_expense']['amount'] ?? [];
            foreach($other_names as $i=>$name){
                $amt=floatval($other_amount[$i]??0); $name=trim($name);
                if($name!=='' && $amt>0){
                    $insertStmt->execute([$order_id,'other_expense',null,null,1,$amt,$amt]);
                    $total+=$amt;
                }
            }

            $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?")->execute([round($total,2),$order_id]);
            $pdo->commit();

            header("Location: orders.php?order_id=".urlencode($order_id));
            exit;

        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $message="❌ Failed to create order: ".$e->getMessage();
        }
    }
}

// ---------------------------
// Render Page
// ---------------------------
ob_start();
?>

<!-- HTML Form Here (the one you already have) -->
<!-- Include all tables: Products, Split, Ducted, Personnel, Equipment, Other Expenses -->
<!-- Include JS for quantity changes, subtotal, totals, other expenses -->

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
