<?php
/**
 * Password Hash Checker
 * Shows which students have password hashes and tests password verification
 */

require_once('../config/database.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Checker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold mb-4">🔐 Password Hash Checker</h1>
            <p class="text-gray-600 mb-4">This tool shows student password hashes and lets you test password verification.</p>
        </div>

        <?php
        // Get all students
        $result = $conn->query("SELECT student_id, full_name, password_hash, created_at, updated_at FROM students ORDER BY created_at DESC");
        
        if ($result->num_rows > 0):
        ?>
        
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left">Student ID</th>
                        <th class="px-4 py-3 text-left">Full Name</th>
                        <th class="px-4 py-3 text-left">Password Hash</th>
                        <th class="px-4 py-3 text-left">Created</th>
                        <th class="px-4 py-3 text-left">Test Password</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $result->fetch_assoc()): ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm"><?php echo htmlspecialchars($student['student_id']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td class="px-4 py-3">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">
                                <?php echo substr($student['password_hash'], 0, 30) . '...'; ?>
                            </code>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?php echo date('M d, Y H:i', strtotime($student['created_at'])); ?>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="test_student_id" value="<?php echo $student['student_id']; ?>">
                                <input type="hidden" name="test_hash" value="<?php echo $student['password_hash']; ?>">
                                <input 
                                    type="text" 
                                    name="test_password" 
                                    placeholder="Enter password to test"
                                    class="border rounded px-2 py-1 text-sm"
                                    required
                                >
                                <button 
                                    type="submit"
                                    name="test_verify"
                                    class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600"
                                >
                                    Test
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
            No students found in database.
        </div>
        
        <?php endif; ?>

        <?php
        // Handle password verification test
        if (isset($_POST['test_verify'])) {
            $student_id = $_POST['test_student_id'];
            $password = $_POST['test_password'];
            $hash = $_POST['test_hash'];
            
            $isValid = password_verify($password, $hash);
            
            if ($isValid) {
                echo '<div class="mt-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">';
                echo '✅ <strong>Password MATCHES!</strong><br>';
                echo 'Student ID: ' . htmlspecialchars($student_id) . '<br>';
                echo 'Password: "' . htmlspecialchars($password) . '" is correct!';
                echo '</div>';
            } else {
                echo '<div class="mt-6 bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded">';
                echo '❌ <strong>Password DOES NOT MATCH!</strong><br>';
                echo 'Student ID: ' . htmlspecialchars($student_id) . '<br>';
                echo 'Password: "' . htmlspecialchars($password) . '" is incorrect.';
                echo '</div>';
            }
        }
        ?>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-bold mb-2">💡 How Password Verification Works:</h3>
            <ol class="list-decimal list-inside space-y-1 text-sm">
                <li><strong>password_hash()</strong> creates a one-way hash (cannot be reversed)</li>
                <li><strong>password_verify()</strong> checks if a password matches the hash</li>
                <li>If a student was created with student_id as default password, try entering their student_id</li>
                <li>If password was captured from UCAM login, try their actual UCAM password</li>
            </ol>
        </div>

        <div class="mt-4 text-center">
            <a href="../login.html" class="text-blue-600 hover:text-blue-800">← Back to Login</a>
        </div>
    </div>
</body>
</html>
