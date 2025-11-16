<?php
require "admin_only.php";
require "config.php";
require "layout.php";

$msg = "";

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $role = trim($_POST['role']);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $pass, $role]);

    $msg = "User added successfully!";
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $pass = trim($_POST['password']);

    if ($pass) {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $pass, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $id]);
    }

    $msg = "User updated successfully!";
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    $msg = "User deleted successfully!";
}

// Fetch all users
$stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-semibold"></h2>
    <button id="openAddModalBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Add User
    </button>
</div>

<?php if ($msg): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="overflow-x-auto bg-white p-4 rounded-xl shadow">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="bg-gray-100 text-gray-700 border-b">
                <th class="p-2 border">ID</th>
                <th class="p-2 border">Name</th>
                <th class="p-2 border">Email</th>
                <th class="p-2 border">Role</th>
                <th class="p-2 border">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="text-center p-4 text-gray-500">No users found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-2 border text-center"><?= $user['id'] ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($user['name']) ?></td>
                        <td class="p-2 border"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="p-2 border text-center"><?= htmlspecialchars($user['role']) ?></td>
                        <td class="p-2 border text-center space-x-2">
                            <button class="bg-yellow-400 text-white px-2 py-1 rounded editBtn"
                                data-id="<?= $user['id'] ?>"
                                data-name="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>"
                                data-role="<?= $user['role'] ?>">
                                Edit
                            </button>
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="bg-red-600 text-white px-2 py-1 rounded">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-xl w-96 shadow-lg relative">
        <button id="closeAddModalBtn" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>
        <h3 class="text-lg font-semibold mb-4">Add User</h3>
        <form method="post">
            <input type="hidden" name="add_user" value="1">
            <div class="mb-3">
                <input type="text" name="name" placeholder="Name" required class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-3">
                <input type="email" name="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-3">
                <input type="password" name="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-4">
                <select name="role" class="w-full border rounded px-3 py-2">
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white w-full py-2 rounded hover:bg-blue-700">Save</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-xl w-96 shadow-lg relative">
        <button id="closeEditModalBtn" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>
        <h3 class="text-lg font-semibold mb-4">Edit User</h3>
        <form method="post">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="id" id="editId">
            <div class="mb-3">
                <input type="text" name="name" id="editName" placeholder="Name" required class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-3">
                <input type="email" name="email" id="editEmail" placeholder="Email" required class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-3">
                <input type="password" name="password" id="editPassword" placeholder="New Password (leave blank to keep current)" class="w-full border rounded px-3 py-2">
            </div>
            <div class="mb-4">
                <select name="role" id="editRole" class="w-full border rounded px-3 py-2">
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
            </div>
            <button type="submit" class="bg-yellow-400 text-white w-full py-2 rounded hover:bg-yellow-500">Update</button>
        </form>
    </div>
</div>

<script>
// Add Modal toggle
const addModal = document.getElementById('addModal');
document.getElementById('openAddModalBtn').onclick = () => addModal.classList.remove('hidden');
document.getElementById('closeAddModalBtn').onclick = () => addModal.classList.add('hidden');
window.onclick = (e) => { if(e.target === addModal) addModal.classList.add('hidden'); };

// Edit Modal toggle
const editModal = document.getElementById('editModal');
const editButtons = document.querySelectorAll('.editBtn');
editButtons.forEach(btn => {
    btn.onclick = () => {
        document.getElementById('editId').value = btn.dataset.id;
        document.getElementById('editName').value = btn.dataset.name;
        document.getElementById('editEmail').value = btn.dataset.email;
        document.getElementById('editRole').value = btn.dataset.role;
        document.getElementById('editPassword').value = '';
        editModal.classList.remove('hidden');
    };
});
document.getElementById('closeEditModalBtn').onclick = () => editModal.classList.add('hidden');
window.onclick = (e) => { if(e.target === editModal) editModal.classList.add('hidden'); };
</script>

<?php
$content = ob_get_clean();
renderLayout("Users", $content);
?>
