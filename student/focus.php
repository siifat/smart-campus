<?php
/**
 * Focus Session - Pomodoro Timer with Weather & Spotify Integration
 * UIU Smart Campus
 */
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['student_id'])) {
    header('Location: ../login.html?error=unauthorized');
    exit;
}

require_once('../config/database.php');

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Fetch student data including points
$stmt = $conn->prepare("SELECT *, COALESCE(total_points, 0) as total_points FROM students WHERE student_id = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$total_points = $student['total_points'] ?? 0;
$stmt->close();

// Set page variables for topbar
$page_title = 'Focus Session';
$page_icon = 'fas fa-brain';
$show_page_title = true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Session - UIU Smart Campus</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --card-bg: rgba(255, 255, 255, 0.9);
            --sidebar-width: 280px;
            --topbar-height: 72px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --card-bg: rgba(30, 41, 59, 0.9);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            transition: all 0.3s ease;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #f68b1f 0%, #fbbf24 50%, #f68b1f 100%);
            padding: 24px;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(246, 139, 31, 0.15);
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #d97706 0%, #f59e0b 50%, #d97706 100%);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-logo i {
            font-size: 32px;
            color: white;
        }
        
        .sidebar-logo span {
            font-size: 20px;
            font-weight: 800;
            color: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item i {
            font-size: 20px;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            padding-top: calc(var(--topbar-height) + 24px);
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 90;
            box-shadow: 0 2px 12px var(--shadow-color);
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-secondary);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .icon-btn:hover {
            background: #f68b1f;
            color: white;
            transform: translateY(-2px);
        }
        
        .icon-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid var(--bg-primary);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        /* Animated Weather Background */
        .weather-background {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            bottom: 0;
            z-index: 0;
            transition: all 1.5s ease;
            pointer-events: none;
            overflow: hidden;
        }
        
        .weather-background.clear-day {
            background: linear-gradient(180deg, #4facfe 0%, #00f2fe 50%, #fbbf24 100%);
            animation: sunriseGlow 20s ease-in-out infinite;
        }
        
        .weather-background.clear-night {
            background: linear-gradient(180deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        }
        
        .weather-background.cloudy {
            background: linear-gradient(180deg, #bdc3c7 0%, #8e9eab 50%, #536976 100%);
        }
        
        .weather-background.rainy {
            background: linear-gradient(180deg, #283048 0%, #4b6cb7 50%, #859398 100%);
        }
        
        .weather-background.snowy {
            background: linear-gradient(180deg, #e6dada 0%, #adb5bd 50%, #6c757d 100%);
        }
        
        .weather-background.thunderstorm {
            background: linear-gradient(180deg, #141e30 0%, #243b55 50%, #232526 100%);
            animation: lightning 3s ease-in-out infinite;
        }
        
        /* Sun Animation */
        .sun {
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: radial-gradient(circle, #FFD700, #FFA500);
            box-shadow: 0 0 60px 30px rgba(255, 215, 0, 0.5);
            top: 10%;
            right: 10%;
            animation: sunPulse 4s ease-in-out infinite;
        }
        
        @keyframes sunPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 60px 30px rgba(255, 215, 0, 0.5); }
            50% { transform: scale(1.05); box-shadow: 0 0 80px 40px rgba(255, 215, 0, 0.7); }
        }
        
        @keyframes sunriseGlow {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.1); }
        }
        
        /* Moon Animation */
        .moon {
            position: absolute;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #f4f4f4, #c7c7c7);
            box-shadow: 0 0 50px 20px rgba(200, 200, 220, 0.4);
            top: 8%;
            right: 8%;
            animation: moonGlow 5s ease-in-out infinite;
        }
        
        .moon::before {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            background: rgba(150, 150, 150, 0.3);
            border-radius: 50%;
            top: 30%;
            left: 25%;
        }
        
        .moon::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(150, 150, 150, 0.2);
            border-radius: 50%;
            bottom: 25%;
            right: 30%;
        }
        
        @keyframes moonGlow {
            0%, 100% { box-shadow: 0 0 50px 20px rgba(200, 200, 220, 0.4); }
            50% { box-shadow: 0 0 70px 30px rgba(200, 200, 220, 0.6); }
        }
        
        /* Stars for Night */
        .stars {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            animation: twinkle 3s ease-in-out infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }
        
        /* Clouds Animation */
        .clouds-container {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .cloud {
            position: absolute;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 100px;
            animation: float linear infinite;
        }
        
        .cloud::before,
        .cloud::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 100px;
        }
        
        .cloud::before {
            width: 50px;
            height: 50px;
            top: -25px;
            left: 10px;
        }
        
        .cloud::after {
            width: 60px;
            height: 60px;
            top: -30px;
            right: 10px;
        }
        
        @keyframes float {
            from { transform: translateX(-200px); }
            to { transform: translateX(calc(100vw + 200px)); }
        }
        
        /* Weather Particles */
        .rain-container, .snow-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .rain {
            position: absolute;
            width: 2px;
            height: 25px;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.8));
            animation: rainFall linear infinite;
            opacity: 0.7;
        }
        
        .snow {
            position: absolute;
            width: 10px;
            height: 10px;
            background: radial-gradient(circle, white, rgba(255, 255, 255, 0.6));
            border-radius: 50%;
            animation: snowFall linear infinite;
            opacity: 0.9;
        }
        
        @keyframes rainFall {
            0% { transform: translateY(-100px); opacity: 0; }
            10% { opacity: 0.7; }
            90% { opacity: 0.7; }
            100% { transform: translateY(100vh); opacity: 0; }
        }
        
        @keyframes snowFall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 0; }
            10% { opacity: 0.9; }
            90% { opacity: 0.9; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        
        /* Lightning Flash */
        @keyframes lightning {
            0%, 90%, 100% { background: linear-gradient(180deg, #141e30 0%, #243b55 50%, #232526 100%); }
            91%, 92% { background: linear-gradient(180deg, #4a5568 0%, #718096 50%, #4a5568 100%); }
            93%, 94% { background: linear-gradient(180deg, #141e30 0%, #243b55 50%, #232526 100%); }
            95% { background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 50%, #94a3b8 100%); }
        }
        
        /* Focus Container */
        .focus-container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Glass Card - iOS Style */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 
                0 8px 32px 0 rgba(31, 38, 135, 0.15),
                0 2px 8px 0 rgba(31, 38, 135, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .glass-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 8px 32px 0 rgba(0, 0, 0, 0.4),
                0 2px 8px 0 rgba(0, 0, 0, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.6), 
                transparent);
            opacity: 0.5;
        }
        
        .glass-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 
                0 16px 48px 0 rgba(31, 38, 135, 0.25),
                0 4px 16px 0 rgba(31, 38, 135, 0.15),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.6);
        }
        
        [data-theme="dark"] .glass-card:hover {
            box-shadow: 
                0 16px 48px 0 rgba(0, 0, 0, 0.5),
                0 4px 16px 0 rgba(0, 0, 0, 0.4),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.15);
        }
        
        /* Timer Display */
        .timer-display {
            font-size: 120px;
            font-weight: 800;
            color: var(--text-primary);
            text-align: center;
            margin: 40px 0;
            font-variant-numeric: tabular-nums;
            letter-spacing: -4px;
        }
        
        .timer-label {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 40px;
        }
        
        /* Timer Controls */
        .timer-controls {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .timer-btn {
            padding: 16px 32px;
            border-radius: 16px;
            border: none;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .timer-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            box-shadow: 
                0 6px 20px 0 rgba(246, 139, 31, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 4px 16px 0 rgba(31, 38, 135, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.5);
        }
        
        [data-theme="dark"] .btn-secondary {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 4px 16px 0 rgba(0, 0, 0, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 
                0 6px 20px 0 rgba(239, 68, 68, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        /* Mode Selector */
        .mode-selector {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        
        .mode-btn {
            padding: 14px 28px;
            border-radius: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 2px 8px 0 rgba(31, 38, 135, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.4);
        }
        
        [data-theme="dark"] .mode-btn {
            background: rgba(30, 41, 59, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 2px 8px 0 rgba(0, 0, 0, 0.2),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .mode-btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 6px 16px 0 rgba(246, 139, 31, 0.2),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.5);
            border-color: rgba(246, 139, 31, 0.5);
        }
        
        .mode-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-color: transparent;
            box-shadow: 
                0 8px 24px 0 rgba(246, 139, 31, 0.4),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        /* Weather Widget - iOS Style */
        .weather-widget {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 
                0 4px 24px 0 rgba(31, 38, 135, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.6);
        }
        
        [data-theme="dark"] .weather-widget {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 4px 24px 0 rgba(0, 0, 0, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .weather-icon {
            font-size: 56px;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.1));
        }
        
        .weather-info {
            flex: 1;
        }
        
        .weather-temp {
            font-size: 38px;
            font-weight: 800;
            color: var(--text-primary);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .weather-desc {
            font-size: 16px;
            color: var(--text-secondary);
            text-transform: capitalize;
            font-weight: 500;
        }
        
        .weather-location {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
            font-weight: 500;
        }
        
        /* Spotify Widget - iOS Style */
        .spotify-widget {
            padding: 24px;
            background: rgba(29, 185, 84, 0.95);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-radius: 20px;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 8px 32px 0 rgba(29, 185, 84, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        [data-theme="dark"] .spotify-widget {
            background: rgba(29, 185, 84, 0.9);
        }
        
        .spotify-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .spotify-header i {
            font-size: 28px;
        }
        
        .spotify-title {
            font-size: 18px;
            font-weight: 700;
        }
        
        .playlist-btn {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 4px;
            box-shadow: 
                0 2px 8px 0 rgba(0, 0, 0, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .playlist-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
            box-shadow: 
                0 4px 12px 0 rgba(0, 0, 0, 0.2),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.4);
        }
        
        /* Todo List in Focus */
        .focus-todo-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 2px 8px 0 rgba(31, 38, 135, 0.08),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.4);
        }
        
        [data-theme="dark"] .focus-todo-item {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 2px 8px 0 rgba(0, 0, 0, 0.2),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.08);
        }
        
        .focus-todo-item:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(4px);
            box-shadow: 
                0 4px 12px 0 rgba(31, 38, 135, 0.12),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.5);
        }
        
        [data-theme="dark"] .focus-todo-item:hover {
            background: rgba(30, 41, 59, 0.6);
        }
        
        .todo-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .todo-checkbox.checked {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            border-color: #f68b1f;
        }
        
        /* Progress Ring */
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.3s ease;
        }
        
        /* Stats Display */
        .focus-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 4px 16px 0 rgba(31, 38, 135, 0.1),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] .stat-item {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 4px 16px 0 rgba(0, 0, 0, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .stat-item:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 8px 24px 0 rgba(31, 38, 135, 0.15),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.6);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #f68b1f;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        /* Achievement Badge */
        .achievement-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #fbbf24, #f68b1f);
            border-radius: 20px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Custom Timer Modal */
        .custom-timer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .custom-timer-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            box-shadow: 
                0 20px 60px 0 rgba(31, 38, 135, 0.3),
                0 4px 16px 0 rgba(31, 38, 135, 0.2),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.6);
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] .custom-timer-content {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 20px 60px 0 rgba(0, 0, 0, 0.5),
                0 4px 16px 0 rgba(0, 0, 0, 0.3),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                transform: translateY(50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .custom-timer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        
        .custom-timer-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .close-modal {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 20px;
            color: var(--text-secondary);
        }
        
        .close-modal:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(90deg);
        }
        
        .time-inputs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .time-input-group {
            text-align: center;
        }
        
        .time-input-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .time-input {
            width: 100%;
            padding: 16px;
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .time-input {
            background: rgba(30, 41, 59, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .time-input:focus {
            outline: none;
            border-color: #f68b1f;
            background: rgba(246, 139, 31, 0.1);
            box-shadow: 0 0 0 4px rgba(246, 139, 31, 0.1);
        }
        
        .quick-presets {
            margin-bottom: 24px;
        }
        
        .preset-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: block;
        }
        
        .preset-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .preset-btn {
            padding: 10px 16px;
            border-radius: 12px;
            border: 2px solid rgba(246, 139, 31, 0.3);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        [data-theme="dark"] .preset-btn {
            background: rgba(30, 41, 59, 0.4);
            border: 2px solid rgba(246, 139, 31, 0.3);
        }
        
        .preset-btn:hover {
            background: rgba(246, 139, 31, 0.2);
            border-color: #f68b1f;
            transform: translateY(-2px);
        }
        
        .preset-btn.active {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            border-color: transparent;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 16px;
            border-radius: 16px;
            border: none;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #f68b1f, #fbbf24);
            color: white;
            box-shadow: 0 6px 20px rgba(246, 139, 31, 0.3);
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(246, 139, 31, 0.4);
        }
        
        .modal-btn-secondary {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-primary);
        }
        
        .modal-btn-secondary:hover {
            background: rgba(0, 0, 0, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar {
                left: 0;
            }
            
            .weather-background {
                left: 0;
            }
            
            .timer-display {
                font-size: 80px;
            }
        }
        
        @media (max-width: 640px) {
            .timer-display {
                font-size: 60px;
            }
            
            .timer-label {
                font-size: 18px;
            }
            
            .timer-btn {
                padding: 12px 24px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php require_once('includes/sidebar.php'); ?>
    <?php require_once('includes/topbar.php'); ?>
    
    <!-- Animated Weather Background -->
    <div class="weather-background clear-day" id="weatherBackground">
        <div class="rain-container" id="rainContainer"></div>
        <div class="snow-container" id="snowContainer"></div>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="focus-container">
            <!-- Header with Weather & Achievements -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Weather Widget -->
                <div class="glass-card">
                    <div class="weather-widget" id="weatherWidget">
                        <div class="weather-icon">
                            <i class="fas fa-cloud-sun"></i>
                        </div>
                        <div class="weather-info">
                            <div class="weather-temp">--°C</div>
                            <div class="weather-desc">Loading weather...</div>
                            <div class="weather-location">
                                <i class="fas fa-map-marker-alt"></i> Detecting location...
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Focus Stats -->
                <div class="glass-card lg:col-span-2">
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                        <i class="fas fa-chart-line"></i> Today's Focus Stats
                    </h3>
                    <div class="focus-stats">
                        <div class="stat-item">
                            <div class="stat-value" id="todaySessions">0</div>
                            <div class="stat-label">Sessions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="todayMinutes">0</div>
                            <div class="stat-label">Minutes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="todayPoints">0</div>
                            <div class="stat-label">Points Earned</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="currentStreak">0</div>
                            <div class="stat-label">Day Streak</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Timer Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Timer -->
                <div class="glass-card lg:col-span-2">
                    <!-- Mode Selector -->
                    <div class="mode-selector">
                        <button class="mode-btn active" data-mode="pomodoro" data-duration="25">
                            <i class="fas fa-brain"></i> Pomodoro (25 min)
                        </button>
                        <button class="mode-btn" data-mode="short-break" data-duration="5">
                            <i class="fas fa-coffee"></i> Short Break (5 min)
                        </button>
                        <button class="mode-btn" data-mode="long-break" data-duration="15">
                            <i class="fas fa-couch"></i> Long Break (15 min)
                        </button>
                        <button class="mode-btn" id="customModeBtn">
                            <i class="fas fa-sliders-h"></i> <span id="customBtnText">Custom</span>
                        </button>
                    </div>
                    
                    <!-- Timer Display -->
                    <div style="position: relative; display: flex; justify-content: center; margin: 20px 0;">
                        <svg width="300" height="300" style="position: absolute;">
                            <circle class="progress-ring" cx="150" cy="150" r="140" 
                                    stroke="var(--border-color)" stroke-width="8" fill="none"/>
                            <circle class="progress-ring progress-ring-circle" id="progressCircle"
                                    cx="150" cy="150" r="140" 
                                    stroke="#f68b1f" stroke-width="8" fill="none"
                                    stroke-dasharray="879.6" stroke-dashoffset="879.6"/>
                        </svg>
                        <div style="position: relative; z-index: 1;">
                            <div class="timer-label" id="timerLabel">Ready to Focus?</div>
                            <div class="timer-display" id="timerDisplay">25:00</div>
                        </div>
                    </div>
                    
                    <!-- Timer Controls -->
                    <div class="timer-controls">
                        <button class="timer-btn btn-primary" id="startBtn">
                            <i class="fas fa-play"></i> Start Focus
                        </button>
                        <button class="timer-btn btn-secondary" id="pauseBtn" style="display: none;">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                        <button class="timer-btn btn-danger" id="resetBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                    
                    <!-- Achievement Notification Area -->
                    <div id="achievementNotification" style="margin-top: 24px; text-align: center; min-height: 40px;"></div>
                </div>
                
                <!-- Sidebar: Spotify & Todo -->
                <div class="space-y-6">
                    <!-- Spotify Widget -->
                    <div class="glass-card">
                        <div class="spotify-widget">
                            <div class="spotify-header">
                                <i class="fab fa-spotify"></i>
                                <div class="spotify-title">Focus Music</div>
                            </div>
                            <div style="font-size: 14px; margin-bottom: 12px; opacity: 0.9;">
                                Choose your focus playlist:
                            </div>
                            <div>
                                <button class="playlist-btn" onclick="openSpotifyPlaylist('37i9dQZF1DWZeKCadgRdKQ')">
                                    <i class="fas fa-music"></i> Deep Focus
                                </button>
                                <button class="playlist-btn" onclick="openSpotifyPlaylist('37i9dQZF1DX8Uebhn9wzrS')">
                                    <i class="fas fa-headphones"></i> Chill Lofi
                                </button>
                                <button class="playlist-btn" onclick="openSpotifyPlaylist('37i9dQZF1DX4sWSpwq3LiO')">
                                    <i class="fas fa-cloud"></i> Peaceful Piano
                                </button>
                                <button class="playlist-btn" onclick="openSpotifyPlaylist('37i9dQZF1DX0SM0LYsmbMT')">
                                    <i class="fas fa-rain"></i> Ambient Chill
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Todo List -->
                    <div class="glass-card">
                        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
                            <i class="fas fa-tasks"></i> Quick Tasks
                        </h3>
                        <div id="focusTodoList" style="max-height: 400px; overflow-y: auto;">
                            <!-- Todos will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Custom Timer Modal -->
    <div class="custom-timer-modal" id="customTimerModal">
        <div class="custom-timer-content">
            <div class="custom-timer-header">
                <h2 class="custom-timer-title">Set Custom Timer</h2>
                <button class="close-modal" id="closeModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="time-inputs">
                <div class="time-input-group">
                    <label class="time-input-label">Hours</label>
                    <input type="number" class="time-input" id="hoursInput" min="0" max="23" value="0" />
                </div>
                <div class="time-input-group">
                    <label class="time-input-label">Minutes</label>
                    <input type="number" class="time-input" id="minutesInput" min="0" max="59" value="25" />
                </div>
                <div class="time-input-group">
                    <label class="time-input-label">Seconds</label>
                    <input type="number" class="time-input" id="secondsInput" min="0" max="59" value="0" />
                </div>
            </div>
            
            <div class="quick-presets">
                <label class="preset-label">Quick Presets</label>
                <div class="preset-buttons">
                    <button class="preset-btn" data-hours="0" data-minutes="10" data-seconds="0">10 min</button>
                    <button class="preset-btn" data-hours="0" data-minutes="15" data-seconds="0">15 min</button>
                    <button class="preset-btn" data-hours="0" data-minutes="20" data-seconds="0">20 min</button>
                    <button class="preset-btn" data-hours="0" data-minutes="30" data-seconds="0">30 min</button>
                    <button class="preset-btn" data-hours="0" data-minutes="45" data-seconds="0">45 min</button>
                    <button class="preset-btn" data-hours="1" data-minutes="0" data-seconds="0">1 hour</button>
                    <button class="preset-btn" data-hours="1" data-minutes="30" data-seconds="0">1.5 hours</button>
                    <button class="preset-btn" data-hours="2" data-minutes="0" data-seconds="0">2 hours</button>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="modal-btn modal-btn-secondary" id="cancelModalBtn">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="setCustomTimerBtn">Set Timer</button>
            </div>
        </div>
    </div>
    
    <script>
        // Configuration
        const WEATHER_API_KEY = 'bd3885d8c46dc853a9d48c7a035ac27f'; // Replace with your API key from openweathermap.org
        const POMODORO_DURATION = 25 * 60; // 25 minutes
        const SHORT_BREAK_DURATION = 5 * 60; // 5 minutes
        const LONG_BREAK_DURATION = 15 * 60; // 15 minutes
        
        // Timer State
        let timerState = {
            duration: POMODORO_DURATION,
            remaining: POMODORO_DURATION,
            totalDuration: POMODORO_DURATION,
            isRunning: false,
            mode: 'pomodoro',
            interval: null,
            sessionsCompleted: 0,
            totalFocusTime: 0
        };
        
        // DOM Elements
        const timerDisplay = document.getElementById('timerDisplay');
        const timerLabel = document.getElementById('timerLabel');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const resetBtn = document.getElementById('resetBtn');
        const progressCircle = document.getElementById('progressCircle');
        const modeButtons = document.querySelectorAll('.mode-btn');
        
        // Custom Timer Modal Elements
        const customModeBtn = document.getElementById('customModeBtn');
        const customTimerModal = document.getElementById('customTimerModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const setCustomTimerBtn = document.getElementById('setCustomTimerBtn');
        const hoursInput = document.getElementById('hoursInput');
        const minutesInput = document.getElementById('minutesInput');
        const secondsInput = document.getElementById('secondsInput');
        const presetButtons = document.querySelectorAll('.preset-btn');
        const customBtnText = document.getElementById('customBtnText');
        
        // Store custom timer duration
        let customTimerDuration = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadWeather();
            loadFocusStats();
            loadFocusTodos();
            setupModeButtons();
            setupTimerControls();
            setupCustomTimerModal();
            updateDisplay();
        });
        
        // Custom Timer Modal Functions
        function setupCustomTimerModal() {
            // Open modal
            customModeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (customModeBtn.classList.contains('active') && customTimerDuration) {
                    // If custom mode is already active, don't open modal
                    return;
                }
                customTimerModal.style.display = 'flex';
            });
            
            // Close modal
            closeModalBtn.addEventListener('click', closeCustomTimerModal);
            cancelModalBtn.addEventListener('click', closeCustomTimerModal);
            
            // Click outside to close
            customTimerModal.addEventListener('click', (e) => {
                if (e.target === customTimerModal) {
                    closeCustomTimerModal();
                }
            });
            
            // Preset buttons
            presetButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    presetButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    hoursInput.value = btn.dataset.hours;
                    minutesInput.value = btn.dataset.minutes;
                    secondsInput.value = btn.dataset.seconds;
                });
            });
            
            // Validate inputs
            [hoursInput, minutesInput, secondsInput].forEach(input => {
                input.addEventListener('input', () => {
                    const max = parseInt(input.max);
                    const min = parseInt(input.min);
                    let value = parseInt(input.value) || 0;
                    
                    if (value > max) input.value = max;
                    if (value < min) input.value = min;
                    
                    // Remove active state from presets when manually editing
                    presetButtons.forEach(b => b.classList.remove('active'));
                });
                
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        setCustomTimer();
                    }
                });
            });
            
            // Set custom timer
            setCustomTimerBtn.addEventListener('click', setCustomTimer);
        }
        
        function closeCustomTimerModal() {
            customTimerModal.style.display = 'none';
        }
        
        function setCustomTimer() {
            const hours = parseInt(hoursInput.value) || 0;
            const minutes = parseInt(minutesInput.value) || 0;
            const seconds = parseInt(secondsInput.value) || 0;
            
            const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;
            
            if (totalSeconds === 0) {
                alert('Please set a time greater than 0');
                return;
            }
            
            if (totalSeconds > 86400) { // 24 hours
                alert('Maximum timer duration is 24 hours');
                return;
            }
            
            if (timerState.isRunning) {
                if (!confirm('Stop current session and switch to custom timer?')) {
                    closeCustomTimerModal();
                    return;
                }
                stopTimer();
            }
            
            // Set custom timer
            customTimerDuration = totalSeconds;
            timerState.mode = 'custom';
            timerState.duration = totalSeconds;
            timerState.remaining = totalSeconds;
            timerState.totalDuration = totalSeconds;
            
            // Update UI
            modeButtons.forEach(b => b.classList.remove('active'));
            customModeBtn.classList.add('active');
            
            // Update custom button text
            let timeText = '';
            if (hours > 0) timeText += `${hours}h `;
            if (minutes > 0) timeText += `${minutes}m `;
            if (seconds > 0 && hours === 0) timeText += `${seconds}s`;
            customBtnText.textContent = `Custom (${timeText.trim()})`;
            
            // Update timer label
            timerLabel.textContent = 'Custom Timer';
            
            updateDisplay();
            closeCustomTimerModal();
        }
        
        // Mode Selection
        function setupModeButtons() {
            modeButtons.forEach(btn => {
                // Skip custom mode button (handled separately)
                if (btn.id === 'customModeBtn') return;
                
                btn.addEventListener('click', () => {
                    if (timerState.isRunning) {
                        if (!confirm('Stop current session and switch mode?')) return;
                        stopTimer();
                    }
                    
                    modeButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    const mode = btn.dataset.mode;
                    const duration = parseInt(btn.dataset.duration) * 60;
                    
                    timerState.mode = mode;
                    timerState.duration = duration;
                    timerState.remaining = duration;
                    timerState.totalDuration = duration;
                    
                    updateDisplay();
                    updateLabel();
                });
            });
        }
        
        // Timer Controls
        function setupTimerControls() {
            startBtn.addEventListener('click', startTimer);
            pauseBtn.addEventListener('click', pauseTimer);
            resetBtn.addEventListener('click', resetTimer);
        }
        
        function startTimer() {
            timerState.isRunning = true;
            startBtn.style.display = 'none';
            pauseBtn.style.display = 'flex';
            
            if (timerState.mode === 'custom') {
                timerLabel.textContent = 'Stay Focused! 🎯';
            } else {
                timerLabel.textContent = timerState.mode === 'pomodoro' ? 'Stay Focused! 🎯' : 'Take a Break! ☕';
            }
            
            timerState.interval = setInterval(() => {
                timerState.remaining--;
                
                if (timerState.remaining <= 0) {
                    completeSession();
                } else {
                    updateDisplay();
                }
            }, 1000);
        }
        
        function pauseTimer() {
            timerState.isRunning = false;
            clearInterval(timerState.interval);
            startBtn.style.display = 'flex';
            pauseBtn.style.display = 'none';
            timerLabel.textContent = 'Paused';
        }
        
        function stopTimer() {
            timerState.isRunning = false;
            clearInterval(timerState.interval);
            startBtn.style.display = 'flex';
            pauseBtn.style.display = 'none';
        }
        
        function resetTimer() {
            stopTimer();
            timerState.remaining = timerState.duration;
            updateDisplay();
            updateLabel();
        }
        
        function updateLabel() {
            const labels = {
                'pomodoro': 'Ready to Focus?',
                'short-break': 'Time for a Short Break',
                'long-break': 'Time for a Long Break',
                'custom': 'Custom Timer'
            };
            timerLabel.textContent = labels[timerState.mode] || 'Ready to Focus?';
        }
        
        function updateDisplay() {
            const minutes = Math.floor(timerState.remaining / 60);
            const seconds = timerState.remaining % 60;
            timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Update progress ring
            const circumference = 2 * Math.PI * 140;
            const progress = (timerState.duration - timerState.remaining) / timerState.duration;
            const offset = circumference * (1 - progress);
            progressCircle.style.strokeDashoffset = offset;
        }
        
        async function completeSession() {
            stopTimer();
            
            if (timerState.mode === 'pomodoro') {
                timerState.sessionsCompleted++;
                timerState.totalFocusTime += POMODORO_DURATION / 60;
                
                // Play completion sound (optional)
                playCompletionSound();
                
                // Save session to database
                await saveFocusSession(POMODORO_DURATION / 60);
                
                // Show achievement
                checkAndShowAchievements();
                
                // Suggest break
                timerLabel.textContent = '🎉 Session Complete! Take a break!';
                
                // Auto-switch to break
                setTimeout(() => {
                    const breakBtn = timerState.sessionsCompleted % 4 === 0 
                        ? document.querySelector('[data-mode="long-break"]')
                        : document.querySelector('[data-mode="short-break"]');
                    breakBtn.click();
                }, 2000);
            } else {
                timerLabel.textContent = 'Break Complete! Ready for another session?';
                setTimeout(() => {
                    document.querySelector('[data-mode="pomodoro"]').click();
                }, 2000);
            }
            
            loadFocusStats(); // Refresh stats
        }
        
        function playCompletionSound() {
            // Create a simple beep sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
        
        // Save Focus Session
        async function saveFocusSession(duration) {
            try {
                const response = await fetch('api/focus_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'save_session',
                        duration: duration,
                        mode: timerState.mode
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    console.log('Session saved successfully');
                    // Update points in topbar
                    if (data.points_earned) {
                        const pointsElement = document.getElementById('user-points');
                        if (pointsElement) {
                            const currentPoints = parseInt(pointsElement.textContent.replace(/,/g, ''));
                            pointsElement.textContent = (currentPoints + data.points_earned).toLocaleString();
                        }
                    }
                }
            } catch (error) {
                console.error('Error saving session:', error);
            }
        }
        
        // Load Focus Stats
        async function loadFocusStats() {
            try {
                const response = await fetch('api/focus_session.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('todaySessions').textContent = data.stats.today_sessions || 0;
                    document.getElementById('todayMinutes').textContent = data.stats.today_minutes || 0;
                    document.getElementById('todayPoints').textContent = data.stats.today_points || 0;
                    document.getElementById('currentStreak').textContent = data.stats.current_streak || 0;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        // Weather Integration
        async function loadWeather() {
            if (!WEATHER_API_KEY || WEATHER_API_KEY === 'YOUR_OPENWEATHERMAP_API_KEY') {
                console.warn('Weather API key not configured');
                updateWeatherDisplay({
                    temp: 25,
                    description: 'Clear Sky',
                    city: 'Dhaka',
                    condition: 'clear-day'
                });
                return;
            }
            
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(async (position) => {
                    const { latitude, longitude } = position.coords;
                    
                    try {
                        const response = await fetch(
                            `https://api.openweathermap.org/data/2.5/weather?lat=${latitude}&lon=${longitude}&units=metric&appid=${WEATHER_API_KEY}`
                        );
                        const data = await response.json();
                        
                        const weatherData = {
                            temp: Math.round(data.main.temp),
                            description: data.weather[0].description,
                            city: data.name,
                            condition: getWeatherCondition(data.weather[0].main, data.weather[0].id)
                        };
                        
                        updateWeatherDisplay(weatherData);
                        updateWeatherBackground(weatherData.condition);
                    } catch (error) {
                        console.error('Error fetching weather:', error);
                    }
                }, (error) => {
                    console.warn('Geolocation error:', error);
                });
            }
        }
        
        function getWeatherCondition(main, id) {
            const hour = new Date().getHours();
            const isNight = hour < 6 || hour > 18;
            
            if (main === 'Clear') return isNight ? 'clear-night' : 'clear-day';
            if (main === 'Clouds') return 'cloudy';
            if (main === 'Rain' || main === 'Drizzle') return 'rainy';
            if (main === 'Snow') return 'snowy';
            if (main === 'Thunderstorm') return 'thunderstorm';
            
            return 'clear-day';
        }
        
        function updateWeatherDisplay(data) {
            const icons = {
                'clear-day': 'fa-sun',
                'clear-night': 'fa-moon',
                'cloudy': 'fa-cloud',
                'rainy': 'fa-cloud-rain',
                'snowy': 'fa-snowflake',
                'thunderstorm': 'fa-bolt'
            };
            
            const weatherWidget = document.getElementById('weatherWidget');
            weatherWidget.innerHTML = `
                <div class="weather-icon">
                    <i class="fas ${icons[data.condition] || 'fa-cloud-sun'}"></i>
                </div>
                <div class="weather-info">
                    <div class="weather-temp">${data.temp}°C</div>
                    <div class="weather-desc">${data.description}</div>
                    <div class="weather-location">
                        <i class="fas fa-map-marker-alt"></i> ${data.city}
                    </div>
                </div>
            `;
        }
        
        function updateWeatherBackground(condition) {
            const bg = document.getElementById('weatherBackground');
            bg.className = `weather-background ${condition}`;
            
            // Clear existing elements
            const rainContainer = document.getElementById('rainContainer');
            const snowContainer = document.getElementById('snowContainer');
            
            rainContainer.innerHTML = '';
            snowContainer.innerHTML = '';
            
            // Remove existing weather elements
            const existingSun = bg.querySelector('.sun');
            const existingMoon = bg.querySelector('.moon');
            const existingStars = bg.querySelector('.stars');
            const existingClouds = bg.querySelector('.clouds-container');
            
            if (existingSun) existingSun.remove();
            if (existingMoon) existingMoon.remove();
            if (existingStars) existingStars.remove();
            if (existingClouds) existingClouds.remove();
            
            // Add weather-specific elements
            if (condition === 'clear-day') {
                // Add sun
                const sun = document.createElement('div');
                sun.className = 'sun';
                bg.appendChild(sun);
                
                // Add some light clouds
                addClouds(bg, 3, true);
                
            } else if (condition === 'clear-night') {
                // Add moon
                const moon = document.createElement('div');
                moon.className = 'moon';
                bg.appendChild(moon);
                
                // Add stars
                const starsContainer = document.createElement('div');
                starsContainer.className = 'stars';
                for (let i = 0; i < 50; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    star.style.left = Math.random() * 100 + '%';
                    star.style.top = Math.random() * 60 + '%';
                    star.style.animationDelay = Math.random() * 3 + 's';
                    starsContainer.appendChild(star);
                }
                bg.appendChild(starsContainer);
                
            } else if (condition === 'cloudy') {
                // Add multiple clouds
                addClouds(bg, 6, false);
                
            } else if (condition === 'rainy') {
                // Add rain drops
                for (let i = 0; i < 100; i++) {
                    const rain = document.createElement('div');
                    rain.className = 'rain';
                    rain.style.left = Math.random() * 100 + '%';
                    rain.style.animationDuration = (Math.random() * 0.3 + 0.4) + 's';
                    rain.style.animationDelay = Math.random() * 2 + 's';
                    rainContainer.appendChild(rain);
                }
                
                // Add dark clouds
                addClouds(bg, 5, false);
                
            } else if (condition === 'snowy') {
                // Add snowflakes
                for (let i = 0; i < 50; i++) {
                    const snow = document.createElement('div');
                    snow.className = 'snow';
                    snow.style.left = Math.random() * 100 + '%';
                    snow.style.animationDuration = (Math.random() * 4 + 4) + 's';
                    snow.style.animationDelay = Math.random() * 5 + 's';
                    snowContainer.appendChild(snow);
                }
                
                // Add clouds
                addClouds(bg, 4, false);
                
            } else if (condition === 'thunderstorm') {
                // Add heavy rain
                for (let i = 0; i < 150; i++) {
                    const rain = document.createElement('div');
                    rain.className = 'rain';
                    rain.style.left = Math.random() * 100 + '%';
                    rain.style.animationDuration = (Math.random() * 0.2 + 0.3) + 's';
                    rain.style.animationDelay = Math.random() * 1 + 's';
                    rainContainer.appendChild(rain);
                }
                
                // Add dark clouds
                addClouds(bg, 7, false);
            }
        }
        
        // Helper function to add clouds
        function addClouds(container, count, light = false) {
            const cloudsContainer = document.createElement('div');
            cloudsContainer.className = 'clouds-container';
            
            for (let i = 0; i < count; i++) {
                const cloud = document.createElement('div');
                cloud.className = 'cloud';
                
                // Random size
                const size = Math.random() * 60 + 80;
                cloud.style.width = size + 'px';
                cloud.style.height = size * 0.6 + 'px';
                
                // Random position
                cloud.style.top = Math.random() * 40 + '%';
                cloud.style.left = -200 + 'px';
                
                // Random animation duration
                cloud.style.animationDuration = (Math.random() * 30 + 40) + 's';
                cloud.style.animationDelay = Math.random() * 20 + 's';
                
                // Adjust opacity
                if (!light) {
                    cloud.style.opacity = '0.6';
                }
                
                cloudsContainer.appendChild(cloud);
            }
            
            container.appendChild(cloudsContainer);
        }
        
        // Spotify Integration
        function openSpotifyPlaylist(playlistId) {
            window.open(`https://open.spotify.com/playlist/${playlistId}`, '_blank');
        }
        
        // Load Todos for Focus View
        async function loadFocusTodos() {
            try {
                const response = await fetch('api/todo.php?action=get');
                const data = await response.json();
                
                if (data.success) {
                    renderFocusTodos(data.todos);
                }
            } catch (error) {
                console.error('Error loading todos:', error);
            }
        }
        
        function renderFocusTodos(todos) {
            const container = document.getElementById('focusTodoList');
            
            if (todos.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No tasks yet. Add some from the dashboard!</p>';
                return;
            }
            
            // Show only incomplete tasks, limit to 5
            const incompleteTodos = todos.filter(t => !t.completed).slice(0, 5);
            
            container.innerHTML = incompleteTodos.map(todo => `
                <div class="focus-todo-item" onclick="toggleFocusTodo(${todo.todo_id}, event)">
                    <div class="todo-checkbox ${todo.completed ? 'checked' : ''}" id="focusTodo${todo.todo_id}">
                        ${todo.completed ? '<i class="fas fa-check" style="color: white; font-size: 14px;"></i>' : ''}
                    </div>
                    <span style="flex: 1; color: var(--text-primary); ${todo.completed ? 'text-decoration: line-through; opacity: 0.6;' : ''}">
                        ${escapeHtml(todo.task)}
                    </span>
                </div>
            `).join('');
        }
        
        async function toggleFocusTodo(todoId, event) {
            event.stopPropagation();
            
            try {
                const response = await fetch('api/todo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'toggle',
                        todo_id: todoId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    loadFocusTodos(); // Reload todos
                }
            } catch (error) {
                console.error('Error toggling todo:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Achievements System
        async function checkAndShowAchievements() {
            try {
                const response = await fetch('api/focus_session.php?action=check_achievements');
                const data = await response.json();
                
                if (data.success && data.new_achievements && data.new_achievements.length > 0) {
                    data.new_achievements.forEach((achievement, index) => {
                        setTimeout(() => {
                            showAchievement(achievement);
                        }, index * 1000);
                    });
                }
            } catch (error) {
                console.error('Error checking achievements:', error);
            }
        }
        
        function showAchievement(achievement) {
            const notification = document.getElementById('achievementNotification');
            const badge = document.createElement('div');
            badge.className = 'achievement-badge';
            badge.innerHTML = `
                <i class="fas fa-trophy"></i>
                <span>Achievement Unlocked: ${achievement.title}</span>
            `;
            
            notification.appendChild(badge);
            
            // Remove after 5 seconds
            setTimeout(() => {
                badge.style.animation = 'fadeOut 0.5s ease';
                setTimeout(() => badge.remove(), 500);
            }, 5000);
        }
    </script>
</body>
</html>
