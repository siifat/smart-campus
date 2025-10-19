// ==UserScript==
// @name         UIU Smart Campus Data Fetcher
// @namespace    http://tampermonkey.net/
// @version      2.0.0
// @description  Fetches student data from UCAM portal and syncs to Smart Campus database (including password capture)
// @author       UIU Smart Campus Team
// @match        *://ucam.uiu.ac.bd/Security/Login.aspx*
// @match        *://ucam.uiu.ac.bd/Security/LogIn.aspx*
// @match        *://ucam.uiu.ac.bd/Security/StudentHome.aspx*
// @icon         https://www.google.com/s2/favicons?domain=uiu.ac.bd
// @grant        GM_xmlhttpRequest
// @grant        GM_setValue
// @grant        GM_getValue
// @connect      localhost
// ==/UserScript==

(function() {
    'use strict';

    // Configuration
    const API_BASE_URL = 'http://localhost/smartcampus/api/';
    const SYNC_ENDPOINT = API_BASE_URL + 'sync_student_data.php';

    // Check if we're on the login page
    const isLoginPage = window.location.href.includes('Login.aspx');
    const isStudentHome = window.location.href.includes('StudentHome.aspx');

    // ========================================
    // LOGIN PAGE: Capture Password
    // ========================================
    if (isLoginPage) {
        console.log('UIU Smart Campus: Login page detected - Password capture enabled');
        
        // Intercept form submission to capture password
        const loginForm = document.getElementById('frmLogIn');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('logMain_UserName')?.value;
                const password = document.getElementById('logMain_Password')?.value;
                
                if (username && password) {
                    // Store credentials temporarily (will be used after successful login)
                    GM_setValue('temp_student_id', username);
                    GM_setValue('temp_password', password);
                    GM_setValue('password_captured_at', Date.now());
                    
                    console.log('✅ Password captured for student:', username);
                    console.log('⏳ Credentials will be synced after successful login redirect...');
                }
            }, true); // Use capture phase to ensure we run first
        }
        
        // Add a visual indicator that password capture is active
        const indicator = document.createElement('div');
        indicator.innerHTML = `
            <div style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 20px;
                border-radius: 10px;
                font-family: Arial, sans-serif;
                font-size: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                z-index: 9999;
                animation: slideIn 0.5s ease-out;
            ">
                🔐 Smart Campus Password Capture Active
            </div>
            <style>
                @keyframes slideIn {
                    from { transform: translateY(100px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            </style>
        `;
        document.body.appendChild(indicator);
        
        return; // Exit here for login page
    }

    // ========================================
    // STUDENT HOME PAGE: Data Sync
    // ========================================
    if (!isStudentHome) {
        return; // Not on the right page
    }

    // Initialize the UI button
    function initializeUI() {
        // Check if button already exists
        if (document.getElementById('smartcampus-fetcher-container')) {
            return;
        }

        // Create container
        const container = document.createElement('div');
        container.id = 'smartcampus-fetcher-container';
        container.innerHTML = `
            <div style="
                position: fixed;
                top: 100px;
                right: 20px;
                z-index: 9999;
                font-family: Arial, sans-serif;
            ">
                <div id="smartcampus-sync-btn" style="
                    background: #007bff;
                    color: white;
                    padding: 12px 16px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 13px;
                    font-weight: bold;
                    box-shadow: 0 2px 10px rgba(0,123,255,0.3);
                    transition: 0.3s;
                    margin-bottom: 8px;
                    text-align: center;
                " 
                onmouseover="this.style.background='#0056b3'"
                onmouseout="this.style.background='#007bff'">
                    📚 Smart Campus<br>📊 Sync Data
                </div>
                <div id="smartcampus-test-btn" style="
                    background: #6c757d;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 11px;
                    font-weight: bold;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    transition: 0.3s;
                    text-align: center;
                " 
                onmouseover="this.style.background='#5a6268'"
                onmouseout="this.style.background='#6c757d'">
                    🔍 Test Elements
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Add event listeners
        document.getElementById('smartcampus-sync-btn').addEventListener('click', syncData);
        document.getElementById('smartcampus-test-btn').addEventListener('click', testDataExtraction);
    }

    // Extract student data from the page
    function extractStudentData() {
        const data = {};

        try {
            // Basic Information
            data.student_id = document.getElementById('ctl00_MainContainer_Label1')?.innerText.trim() || '';
            data.full_name = document.getElementById('ctl00_MainContainer_SI_Name')?.innerText.trim() || '';
            data.blood_group = document.getElementById('ctl00_MainContainer_SI_BloodGroup')?.innerText.trim() || '';
            data.date_of_birth = document.getElementById('ctl00_MainContainer_SI_DOB')?.innerText.trim() || '';
            data.phone = document.getElementById('ctl00_MainContainer_SI_Phone')?.innerText.trim() || '';
            data.father_name = document.getElementById('ctl00_MainContainer_SI_FatherName')?.innerText.trim() || '';
            data.mother_name = document.getElementById('ctl00_MainContainer_SI_MotherName')?.innerText.trim() || '';
            
            // Profile Picture
            const imgElement = document.getElementById('ctl00_MainContainer_SI_Image');
            data.profile_picture = imgElement?.src || '';

            // ========================================
            // PASSWORD RETRIEVAL FROM TEMPORARY STORAGE
            // ========================================
            const tempStudentId = GM_getValue('temp_student_id', '');
            const tempPassword = GM_getValue('temp_password', '');
            const capturedAt = GM_getValue('password_captured_at', 0);
            
            // Check if password was captured within last 5 minutes and matches current student
            const fiveMinutes = 5 * 60 * 1000;
            if (tempPassword && tempStudentId === data.student_id && (Date.now() - capturedAt) < fiveMinutes) {
                data.password = tempPassword;
                console.log('✅ Password retrieved from login capture for student:', data.student_id);
                
                // Clear temporary storage after successful retrieval
                GM_setValue('temp_password', '');
                GM_setValue('temp_student_id', '');
                GM_setValue('password_captured_at', 0);
            } else {
                console.warn('⚠️ No password available. Please login again to capture password.');
                data.password = null;
            }

            // Program Information
            data.program_id = document.getElementById('ctl00_MainContainer_hdnProgramId')?.value || '';

            // Academic Status
            data.transcript_cgpa = document.getElementById('ctl00_MainContainer_Status_CGPA')?.innerText.trim() || '0';
            data.completed_credits = document.getElementById('ctl00_MainContainer_Status_CompletedCr')?.innerText.trim() || '0';

            // Financial Information
            data.total_billed = document.getElementById('ctl00_MainContainer_FI_TotalBilled')?.innerText.replace(/[^\d.]/g, '') || '0';
            data.current_balance = document.getElementById('ctl00_MainContainer_FI_CurrentBalance')?.innerText.replace(/[^\d.-]/g, '') || '0';
            data.total_waived = document.getElementById('ctl00_MainContainer_FI_TotalWaved')?.innerText.replace(/[^\d.]/g, '') || '0';
            data.total_paid = document.getElementById('ctl00_MainContainer_FI_TotalPaid')?.innerText.replace(/[^\d.]/g, '') || '0';

            // Advisor Information
            data.advisor_name = document.getElementById('ctl00_MainContainer_lblAdvisorName')?.innerText.trim() || '';
            data.advisor_initial = document.getElementById('ctl00_MainContainer_lblAdvisorInitial')?.innerText.trim() || '';
            data.advisor_email = document.getElementById('ctl00_MainContainer_lblAdvisorEmail')?.innerText.trim() || '';
            data.advisor_phone = document.getElementById('ctl00_MainContainer_lblAdvisorPhone')?.innerText.trim() || '';
            data.advisor_room = document.getElementById('ctl00_MainContainer_lblAdvisorRoom')?.innerText.trim() || '';

            // Current Trimester Information
            const currentTrimesterText = document.getElementById('ctl00_lblCurrent')?.innerText.trim() || '';
            // Parse trimester: "253 - Fall 2025 (Semester), 252 - Summer 2025 (Trimester)"
            // We need the Trimester part only (not Semester)
            const trimesterMatch = currentTrimesterText.match(/(\d+)\s*-\s*([^(]+)\s*\(Trimester\)/);
            if (trimesterMatch) {
                data.current_trimester_code = trimesterMatch[1]; // e.g., "252"
                data.current_trimester_name = trimesterMatch[2].trim(); // e.g., "Summer 2025"
                data.current_trimester = `${trimesterMatch[1]} - ${trimesterMatch[2].trim()}`; // e.g., "252 - Summer 2025"
            } else {
                // Fallback: if no trimester found, use the full text
                data.current_trimester = currentTrimesterText;
                data.current_trimester_code = '';
                data.current_trimester_name = '';
            }

            // Extract Class Routine
            data.class_routine = extractClassRoutine();

            // Extract Attendance Summary
            data.attendance = extractAttendanceSummary();

            // Extract Academic Results
            data.academic_results = extractAcademicResults();

            console.log('Extracted Student Data:', data);
            return data;

        } catch (error) {
            console.error('Error extracting student data:', error);
            return null;
        }
    }

    // Extract class routine from the table
    function extractClassRoutine() {
        const routine = [];
        const routineTable = document.getElementById('ctl00_MainContainer_Class_Schedule');
        
        if (!routineTable) return routine;

        const rows = routineTable.querySelectorAll('tr');
        let currentDay = '';

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            
            if (cells.length === 1 && cells[0].classList.contains('blue')) {
                // This is a day header
                currentDay = cells[0].innerText.trim();
            } else if (cells.length === 2 && currentDay) {
                // This is a class entry
                const courseCode = cells[0].innerText.trim();
                const time = cells[1].innerText.trim();
                const title = row.getAttribute('title') || '';

                const [startTime, endTime] = time.split('-').map(t => t.trim());

                routine.push({
                    day: currentDay,
                    course_code: courseCode,
                    course_name: title,
                    start_time: convertTo24Hour(startTime),
                    end_time: convertTo24Hour(endTime)
                });
            }
        });

        return routine;
    }

    // Convert 12-hour time format to 24-hour
    function convertTo24Hour(time12h) {
        if (!time12h) return '';
        
        const [time, period] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
        
        hours = parseInt(hours);
        
        if (period === 'PM' && hours !== 12) {
            hours += 12;
        } else if (period === 'AM' && hours === 12) {
            hours = 0;
        }
        
        return `${String(hours).padStart(2, '0')}:${minutes}:00`;
    }

    // Extract attendance summary
    function extractAttendanceSummary() {
        const attendance = [];
        
        // Try to get attendance data from the chart or table
        // The data is loaded via AJAX, so we need to wait or extract from the API call
        
        try {
            // Check if attendance data is available in the page
            const attendanceContainer = document.getElementById('attendancesummary_std');
            
            if (attendanceContainer) {
                // Try to extract from Highcharts data
                const chartData = window.Highcharts?.charts?.find(chart => 
                    chart?.renderTo?.id === 'attendancesummary_std'
                );
                
                if (chartData && chartData.series) {
                    const categories = chartData.xAxis[0].categories || [];
                    const presentSeries = chartData.series.find(s => s.name === 'Present');
                    const absentSeries = chartData.series.find(s => s.name === 'Absent');
                    const remainingSeries = chartData.series.find(s => s.name === 'Remaining');
                    
                    categories.forEach((courseCode, index) => {
                        attendance.push({
                            course_code: courseCode,
                            present_count: presentSeries?.data[index]?.y || 0,
                            absent_count: absentSeries?.data[index]?.y || 0,
                            remaining_classes: remainingSeries?.data[index]?.y || 0
                        });
                    });
                }
            }
        } catch (error) {
            console.error('Error extracting attendance:', error);
        }
        
        return attendance;
    }

    // Extract academic results (GPA history)
    function extractAcademicResults() {
        const results = [];
        
        try {
            const chartData = window.Highcharts?.charts?.find(chart => 
                chart?.renderTo?.id === 'academicresult_std'
            );
            
            if (chartData && chartData.series) {
                const categories = chartData.xAxis[0].categories || [];
                const cgpaSeries = chartData.series.find(s => s.name === 'CGPA');
                const gpaSeries = chartData.series.find(s => s.name === 'GPA');
                
                categories.forEach((trimesterInfo, index) => {
                    results.push({
                        trimester: trimesterInfo,
                        cgpa: cgpaSeries?.data[index]?.y || 0,
                        gpa: gpaSeries?.data[index]?.y || 0
                    });
                });
            }
        } catch (error) {
            console.error('Error extracting academic results:', error);
        }
        
        return results;
    }

    // Test data extraction and show in console
    function testDataExtraction() {
        console.log('=== Testing Data Extraction ===');
        const data = extractStudentData();
        
        if (data) {
            console.log('✅ Data extraction successful!');
            console.log('Student ID:', data.student_id);
            console.log('Full Name:', data.full_name);
            console.log('CGPA:', data.transcript_cgpa);
            console.log('Completed Credits:', data.completed_credits);
            console.log('Class Routine Items:', data.class_routine.length);
            console.log('Attendance Records:', data.attendance.length);
            console.log('Academic Results:', data.academic_results.length);
            console.log('\nFull Data Object:', data);
            
            alert('✅ Data extraction test completed!\n\nCheck the browser console (F12) for detailed results.');
        } else {
            console.error('❌ Data extraction failed!');
            alert('❌ Data extraction test failed!\n\nCheck the browser console (F12) for error details.');
        }
    }

    // Sync data to the database
    function syncData() {
        const syncBtn = document.getElementById('smartcampus-sync-btn');
        const originalHTML = syncBtn.innerHTML;
        
        // Show loading state
        syncBtn.innerHTML = '⏳ Syncing...';
        syncBtn.style.pointerEvents = 'none';

        const data = extractStudentData();

        if (!data || !data.student_id) {
            alert('❌ Failed to extract student data. Please make sure you are logged in to UCAM portal.');
            syncBtn.innerHTML = originalHTML;
            syncBtn.style.pointerEvents = 'auto';
            return;
        }

        // Send data to the server
        GM_xmlhttpRequest({
            method: 'POST',
            url: SYNC_ENDPOINT,
            headers: {
                'Content-Type': 'application/json',
            },
            data: JSON.stringify(data),
            onload: function(response) {
                syncBtn.innerHTML = originalHTML;
                syncBtn.style.pointerEvents = 'auto';

                try {
                    const result = JSON.parse(response.responseText);
                    
                    if (result.success) {
                        // Success animation
                        syncBtn.innerHTML = '✅ Synced!';
                        syncBtn.style.background = '#28a745';
                        
                        setTimeout(() => {
                            syncBtn.innerHTML = originalHTML;
                            syncBtn.style.background = '#007bff';
                        }, 3000);
                        
                        console.log('Sync successful:', result);
                        
                        const passwordStatus = result.data.password_synced 
                            ? '✅ Password: Captured & Synced' 
                            : '⚠️ Password: Not available (login again to capture)';
                        
                        const trimesterInfo = result.data.current_trimester 
                            ? '\nCurrent Trimester: ' + result.data.current_trimester 
                            : '';
                        
                        alert('✅ Data synced successfully to Smart Campus database!\n\n' + 
                              'Student: ' + data.full_name + '\n' +
                              'ID: ' + data.student_id + '\n' +
                              passwordStatus + 
                              trimesterInfo + '\n' +
                              'Courses synced: ' + data.class_routine.length + '\n\n' +
                              (result.data.password_synced 
                                ? 'You can now login to Smart Campus with your UCAM password!' 
                                : 'Please login to UCAM again to capture your password.'));
                    } else {
                        // Error
                        syncBtn.innerHTML = '❌ Failed';
                        syncBtn.style.background = '#dc3545';
                        
                        setTimeout(() => {
                            syncBtn.innerHTML = originalHTML;
                            syncBtn.style.background = '#007bff';
                        }, 3000);
                        
                        console.error('Sync error:', result);
                        alert('❌ Sync failed: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error parsing response:', error);
                    alert('❌ Error communicating with server: ' + error.message);
                }
            },
            onerror: function(error) {
                syncBtn.innerHTML = originalHTML;
                syncBtn.style.pointerEvents = 'auto';
                
                console.error('Request failed:', error);
                alert('❌ Failed to connect to Smart Campus server.\n\n' +
                      'Make sure:\n' +
                      '1. XAMPP is running\n' +
                      '2. The API endpoint exists at: ' + SYNC_ENDPOINT + '\n' +
                      '3. CORS is properly configured');
            }
        });
    }

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Wait for page to load completely
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeUI);
    } else {
        // Wait a bit for AJAX content to load
        setTimeout(initializeUI, 2000);
    }

    console.log('UIU Smart Campus Data Fetcher v1.0.0 loaded successfully!');
})();
