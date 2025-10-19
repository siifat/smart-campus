<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank Integration Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold mb-2 text-gray-900">
                <i class="fas fa-graduation-cap text-orange-500 mr-3"></i>
                Question Bank Integration Test
            </h1>
            <p class="text-gray-600 mb-6">
                Testing the integration of UIUQuestionBank folder with Resources page
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>Files Modified
                    </h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>✅ student/api/resources.php (API)</li>
                        <li>✅ student/resources.php (Frontend)</li>
                        <li>✅ student/viewer.php (PDF Viewer)</li>
                    </ul>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-2">
                        <i class="fas fa-star mr-2"></i>New Features
                    </h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>✅ Question Bank scanning</li>
                        <li>✅ Abbreviation search (e.g., "DBMS")</li>
                        <li>✅ Question Bank filter button</li>
                        <li>✅ Integrated viewer support</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h2 class="text-2xl font-bold mb-4 text-gray-900">
                <i class="fas fa-flask text-purple-500 mr-3"></i>
                Test Results
            </h2>
            
            <div id="testResults" class="space-y-4">
                <div class="border-l-4 border-yellow-500 bg-yellow-50 p-4">
                    <p class="font-semibold text-yellow-800">Running tests...</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900">
                <i class="fas fa-clipboard-check text-indigo-500 mr-3"></i>
                Test Instructions
            </h2>
            
            <div class="space-y-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-2">1. Check File Structure</h3>
                    <p class="text-gray-600 text-sm mb-2">Verify UIUQuestionBank folder exists with question PDFs</p>
                    <button onclick="testFileStructure()" class="bg-indigo-500 text-white px-4 py-2 rounded-lg hover:bg-indigo-600 transition">
                        <i class="fas fa-folder-open mr-2"></i>Test File Structure
                    </button>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-2">2. Test API Endpoint</h3>
                    <p class="text-gray-600 text-sm mb-2">Check if resources API returns question bank items</p>
                    <button onclick="testAPI()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                        <i class="fas fa-plug mr-2"></i>Test API Endpoint
                    </button>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-2">3. Test Resources Page</h3>
                    <p class="text-gray-600 text-sm mb-2">Visit the resources page and verify question bank items appear</p>
                    <a href="student/resources.php" target="_blank" class="inline-block bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Open Resources Page
                    </a>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-2">4. Test Search & Filter</h3>
                    <p class="text-gray-600 text-sm mb-2">Try these searches:</p>
                    <ul class="text-sm text-gray-600 space-y-1 ml-4">
                        <li>• Search "CSE3521" (exact course code)</li>
                        <li>• Search "DBMS" (abbreviation)</li>
                        <li>• Click "Question Bank" filter button</li>
                        <li>• Filter by "Past Papers" category</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addTestResult(title, message, type = 'info') {
            const container = document.getElementById('testResults');
            const colors = {
                success: 'border-green-500 bg-green-50 text-green-800',
                error: 'border-red-500 bg-red-50 text-red-800',
                info: 'border-blue-500 bg-blue-50 text-blue-800',
                warning: 'border-yellow-500 bg-yellow-50 text-yellow-800'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            const result = document.createElement('div');
            result.className = `border-l-4 ${colors[type]} p-4 rounded`;
            result.innerHTML = `
                <p class="font-semibold">
                    <i class="fas ${icons[type]} mr-2"></i>${title}
                </p>
                <p class="text-sm mt-1">${message}</p>
            `;
            
            container.appendChild(result);
        }

        function testFileStructure() {
            const container = document.getElementById('testResults');
            container.innerHTML = '<div class="border-l-4 border-blue-500 bg-blue-50 p-4"><p class="font-semibold text-blue-800">Testing file structure...</p></div>';
            
            fetch('UIUQuestionBank/question/')
                .then(response => {
                    if (response.ok) {
                        addTestResult(
                            'File Structure Check',
                            'UIUQuestionBank folder is accessible ✓',
                            'success'
                        );
                    } else {
                        addTestResult(
                            'File Structure Check',
                            'Could not access UIUQuestionBank folder',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    addTestResult(
                        'File Structure Check',
                        'Error: ' + error.message,
                        'error'
                    );
                });
        }

        function testAPI() {
            const container = document.getElementById('testResults');
            container.innerHTML = '<div class="border-l-4 border-blue-500 bg-blue-50 p-4"><p class="font-semibold text-blue-800">Testing API endpoint...</p></div>';
            
            fetch('student/api/resources.php?action=get_resources')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const questionBankItems = data.resources.filter(r => r.source_type === 'questionbank');
                        
                        if (questionBankItems.length > 0) {
                            addTestResult(
                                'API Test - SUCCESS',
                                `Found ${questionBankItems.length} question bank items in API response`,
                                'success'
                            );
                            
                            // Show sample items
                            const sampleItems = questionBankItems.slice(0, 3);
                            let sampleHTML = '<ul class="text-sm mt-2 space-y-1">';
                            sampleItems.forEach(item => {
                                sampleHTML += `<li>• ${item.title} (${item.course_code})</li>`;
                            });
                            sampleHTML += '</ul>';
                            
                            addTestResult(
                                'Sample Question Bank Items',
                                sampleHTML,
                                'info'
                            );
                        } else {
                            addTestResult(
                                'API Test - WARNING',
                                'API works but returned 0 question bank items. Check if UIUQuestionBank folder has PDFs.',
                                'warning'
                            );
                        }
                        
                        addTestResult(
                            'Total Resources',
                            `API returned ${data.resources.length} total resources (uploaded + question bank)`,
                            'info'
                        );
                    } else {
                        addTestResult(
                            'API Test - ERROR',
                            data.message || 'API returned success: false',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    addTestResult(
                        'API Test - ERROR',
                        'Failed to fetch from API: ' + error.message,
                        'error'
                    );
                });
        }

        // Run initial test on page load
        window.onload = function() {
            setTimeout(() => {
                testAPI();
            }, 500);
        };
    </script>
</body>
</html>
