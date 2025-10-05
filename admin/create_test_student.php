<?php
/**
 * Test Student Creation Tool
 * This file helps create test student accounts with hashed passwords
 * 
 * SECURITY: Remove this file in production!
 */

require_once('../config/database.php');

// Function to create a student account
function createTestStudent($conn, $student_id, $password, $full_name, $email, $program_id = 1) {
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare the insert statement
    $stmt = $conn->prepare("
        INSERT INTO students (
            student_id, 
            password_hash, 
            full_name, 
            email, 
            program_id, 
            admission_date,
            status
        ) VALUES (?, ?, ?, ?, ?, CURDATE(), 'active')
        ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            full_name = VALUES(full_name),
            email = VALUES(email)
    ");
    
    $stmt->bind_param("ssssi", $student_id, $password_hash, $full_name, $email, $program_id);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $program_id = intval($_POST['program_id'] ?? 1);
    
    if (empty($student_id) || empty($password) || empty($full_name)) {
        $message = 'Please fill all required fields!';
        $messageType = 'error';
    } elseif (!preg_match('/^\d{10}$/', $student_id)) {
        $message = 'Student ID must be exactly 10 digits!';
        $messageType = 'error';
    } else {
        if (createTestStudent($conn, $student_id, $password, $full_name, $email, $program_id)) {
            $message = "Test student created successfully!<br>Student ID: $student_id<br>Password: $password";
            $messageType = 'success';
        } else {
            $message = 'Failed to create test student!';
            $messageType = 'error';
        }
    }
}

// Get available programs
$programs_query = "SELECT program_id, program_name, program_code FROM programs ORDER BY program_name";
$programs_result = $conn->query($programs_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test Student - UIU Smart Campus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-orange-50 min-h-screen py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <!-- Warning Banner -->
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <p class="font-bold">⚠️ DEVELOPMENT TOOL ONLY</p>
            <p class="text-sm">This file should be removed in production for security reasons!</p>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Create Test Student Account</h1>
                <p class="text-gray-600">Generate test student accounts for development</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Student ID -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Student ID <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="student_id" 
                        placeholder="1234567890 (10 digits)"
                        pattern="\d{10}"
                        maxlength="10"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    >
                    <p class="text-xs text-gray-500 mt-1">Must be exactly 10 digits</p>
                </div>

                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="full_name" 
                        placeholder="John Doe"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    >
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Email
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="student@example.com"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    >
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="password" 
                        placeholder="Enter password"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    >
                    <p class="text-xs text-gray-500 mt-1">Plain text (will be hashed automatically)</p>
                </div>

                <!-- Program -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Program
                    </label>
                    <select 
                        name="program_id"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    >
                        <?php while ($program = $programs_result->fetch_assoc()): ?>
                            <option value="<?php echo $program['program_id']; ?>">
                                <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-orange-500 to-amber-500 text-white font-bold py-3 rounded-lg hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200"
                >
                    Create Test Student
                </button>
            </form>

            <!-- Quick Test Accounts -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-4">📝 Quick Test Accounts</h3>
                <div class="bg-gray-50 rounded-lg p-4 text-sm space-y-2">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-mono font-semibold">1234567890</span>
                            <span class="text-gray-600 ml-2">/ password: <span class="font-mono">test123</span></span>
                        </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="student_id" value="1234567890">
                            <input type="hidden" name="password" value="test123">
                            <input type="hidden" name="full_name" value="Test Student One">
                            <input type="hidden" name="email" value="test1@student.uiu.ac.bd">
                            <input type="hidden" name="program_id" value="1">
                            <button type="submit" class="px-3 py-1 bg-orange-500 text-white text-xs rounded hover:bg-orange-600">
                                Create
                            </button>
                        </form>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-mono font-semibold">0987654321</span>
                            <span class="text-gray-600 ml-2">/ password: <span class="font-mono">demo123</span></span>
                        </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="student_id" value="0987654321">
                            <input type="hidden" name="password" value="demo123">
                            <input type="hidden" name="full_name" value="Demo Student Two">
                            <input type="hidden" name="email" value="test2@student.uiu.ac.bd">
                            <input type="hidden" name="program_id" value="1">
                            <button type="submit" class="px-3 py-1 bg-orange-500 text-white text-xs rounded hover:bg-orange-600">
                                Create
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-6 text-center">
                <a href="../login.html" class="text-orange-600 hover:text-orange-700 font-semibold">
                    ← Back to Login
                </a>
            </div>
        </div>

        <!-- Usage Instructions -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm">
            <h4 class="font-semibold text-blue-900 mb-2">💡 Usage Instructions</h4>
            <ol class="list-decimal list-inside space-y-1 text-blue-800">
                <li>Create a test student account using the form above</li>
                <li>Go to the login page and use the credentials</li>
                <li>Test the student dashboard functionality</li>
                <li><strong>Remember to delete this file in production!</strong></li>
            </ol>
        </div>
    </div>
</body>
</html>
