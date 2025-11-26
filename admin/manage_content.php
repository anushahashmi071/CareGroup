<?php
ob_start();
// admin/manage_content.php - Manage Website Content (Diseases, News, Inventions)
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config.php';
requireRole('admin');


// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

// if ($stmt->execute()) {
//     $message = 'News deleted successfully!';
//     header('Location: manage_content.php?tab=' . $tab); // Add this
//     exit();
// }

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'diseases';
$message = '';

// Handle Add Disease
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_disease'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO diseases (disease_name, category, description, symptoms, prevention, cure) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $_POST['disease_name'], $_POST['category'], $_POST['description'], $_POST['symptoms'], $_POST['prevention'], $_POST['cure']);
    if ($stmt->execute())
        $message = 'Disease added successfully!';
    else
        $message = 'Error adding disease: ' . $conn->error;
    $stmt->close();
    $conn->close();
}

// Handle Update Disease
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_disease'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE diseases SET disease_name = ?, category = ?, description = ?, symptoms = ?, prevention = ?, cure = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $_POST['disease_name'], $_POST['category'], $_POST['description'], $_POST['symptoms'], $_POST['prevention'], $_POST['cure'], $_POST['disease_id']);
    if ($stmt->execute())
        $message = 'Disease updated successfully!';
    else
        $message = 'Error updating disease: ' . $conn->error;
    $stmt->close();
    $conn->close();
}

// Handle Delete Disease
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_disease'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM diseases WHERE id = ?");
    $stmt->bind_param("i", $_POST['disease_id']);
    if ($stmt->execute())
        $message = 'Disease deleted successfully!';
    else
        $message = 'Error deleting disease: ' . $conn->error;
    $stmt->close();
    $conn->close();
}


// FINAL WORKING VERSION - News Upload Handler (UPDATED)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_news'])) {
    $conn = getDBConnection();

    // Handle image upload
    $image_url = '';

    if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/news/';

        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'news_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            // MOVE THE FILE
            if (move_uploaded_file($_FILES['news_image']['tmp_name'], $upload_path)) {
                // VERIFY the file was saved
                if (file_exists($upload_path) && filesize($upload_path) > 0) {
                    $image_url = $upload_path; // Store relative path: uploads/news/filename.ext
                    $message = "✓ News added with image!";
                } else {
                    $message = "✓ News added (image save failed)";
                }
            } else {
                $message = "✓ News added (image upload failed)";
            }
        } else {
            $message = "✓ News added (invalid file type)";
        }
    } else {
        $message = "✓ News added (no image)";
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO medical_news (title, content, category, author, image_url, status) VALUES (?, ?, ?, ?, ?, 'published')");
    $stmt->bind_param("sssss", $_POST['title'], $_POST['content'], $_POST['category'], $_POST['author'], $image_url);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>$message</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }

    $stmt->close();
    $conn->close();

    // Refresh page
    echo "<script>setTimeout(function() { location.reload(); }, 1500);</script>";
}

// Handle Update News (UPDATED)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_news'])) {
    $conn = getDBConnection();

    if (!$conn) {
        die("Database connection failed");
    }

    // Handle image upload for update
    $image_url = $_POST['current_image'] ?? '';
    if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/news/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'news_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['news_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($_POST['current_image']) && file_exists($_POST['current_image'])) {
                    unlink($_POST['current_image']);
                }
                $image_url = $upload_path;
            }
        }
    }

    $published_date = !empty($_POST['published_date']) ? $_POST['published_date'] : date('Y-m-d H:i:s');
    $status = $_POST['status'] ?? 'published';

    $stmt = $conn->prepare("UPDATE medical_news SET title = ?, content = ?, category = ?, author = ?, published_date = ?, status = ?, image_url = ? WHERE news_id = ?");

    if ($stmt) {
        $stmt->bind_param("sssssssi", $_POST['title'], $_POST['content'], $_POST['category'], $_POST['author'], $published_date, $status, $image_url, $_POST['news_id']);

        if ($stmt->execute()) {
            $message = 'News updated successfully!';
            echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 1000);</script>";
        } else {
            $message = 'Error updating news: ' . $conn->error;
        }

        $stmt->close();
    }

    $conn->close();
}

// Handle Delete News
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_news'])) {
    $conn = getDBConnection();

    if (!$conn) {
        die("Database connection failed");
    }

    // Get image URL before deletion
    $get_stmt = $conn->prepare("SELECT image_url FROM medical_news WHERE news_id = ?");
    $get_stmt->bind_param("i", $_POST['news_id']);
    $get_stmt->execute();
    $get_stmt->bind_result($image_url);
    $get_stmt->fetch();
    $get_stmt->close();

    // Delete the news
    $stmt = $conn->prepare("DELETE FROM medical_news WHERE news_id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $_POST['news_id']);

        if ($stmt->execute()) {
            // Delete associated image file
            if (!empty($image_url) && file_exists($image_url)) {
                unlink($image_url);
            }
            $message = 'News deleted successfully!';
            echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 1000);</script>";
        } else {
            $message = 'Error deleting news: ' . $conn->error;
        }

        $stmt->close();
    }

    $conn->close();
}


// ===== HANDLE ADD INVENTION - EXACTLY LIKE NEWS =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_invention'])) {
    $conn = getDBConnection();

    $image_url = '';

    if (isset($_FILES['invention_image']) && $_FILES['invention_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/inventions/';

        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['invention_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'invention_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            // MOVE THE FILE
            if (move_uploaded_file($_FILES['invention_image']['tmp_name'], $upload_path)) {
                // VERIFY the file was saved
                if (file_exists($upload_path) && filesize($upload_path) > 0) {
                    $image_url = $upload_path; // Store relative path: uploads/inventions/filename.ext
                    $message = "✓ Invention added with image!";
                } else {
                    $message = "✓ Invention added (image save failed)";
                }
            } else {
                $message = "✓ Invention added (image upload failed)";
            }
        } else {
            $message = "✓ Invention added (invalid file type)";
        }
    } else {
        $message = "✓ Invention added (no image)";
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO inventions (invention_name, category, description, benefits, inventor, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $_POST['invention_name'], $_POST['category'], $_POST['description'], $_POST['benefits'], $_POST['inventor'], $image_url);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>$message</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }

    $stmt->close();
    $conn->close();

    // Refresh page
    echo "<script>setTimeout(function() { location.reload(); }, 1500);</script>";
}

// ===== HANDLE UPDATE INVENTION - EXACTLY LIKE NEWS =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_invention'])) {
    $conn = getDBConnection();

    if (!$conn) {
        die("Database connection failed");
    }

    // Handle image upload for update
    $image_url = $_POST['current_image'] ?? '';
    if (isset($_FILES['invention_image']) && $_FILES['invention_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/inventions/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['invention_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'invention_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['invention_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($_POST['current_image']) && file_exists($_POST['current_image'])) {
                    unlink($_POST['current_image']);
                }
                $image_url = $upload_path;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE inventions SET invention_name = ?, category = ?, description = ?, benefits = ?, inventor = ?, image_url = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $_POST['invention_name'], $_POST['category'], $_POST['description'], $_POST['benefits'], $_POST['inventor'], $image_url, $_POST['invention_id']);

    if ($stmt->execute()) {
        $message = 'Invention updated successfully!';
        echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 1000);</script>";
    } else {
        $message = 'Error updating invention: ' . $conn->error;
    }

    $stmt->close();
    $conn->close();
}

// ===== HANDLE DELETE INVENTION - EXACTLY LIKE NEWS =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_invention'])) {
    $conn = getDBConnection();

    if (!$conn) {
        die("Database connection failed");
    }

    // Get image URL before deletion
    $get_stmt = $conn->prepare("SELECT image_url FROM inventions WHERE id = ?");
    $get_stmt->bind_param("i", $_POST['invention_id']);
    $get_stmt->execute();
    $get_stmt->bind_result($image_url);
    $get_stmt->fetch();
    $get_stmt->close();

    // Delete the invention
    $stmt = $conn->prepare("DELETE FROM inventions WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $_POST['invention_id']);

        if ($stmt->execute()) {
            // Delete associated image file
            if (!empty($image_url) && file_exists($image_url)) {
                unlink($image_url);
            }
            $message = 'Invention deleted successfully!';
            echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 1000);</script>";
        } else {
            $message = 'Error deleting invention: ' . $conn->error;
        }

        $stmt->close();
    }

    $conn->close();
}


// Get content from database
$conn = getDBConnection();

// Get diseases
$result = $conn->query("SELECT * FROM diseases ORDER BY created_at DESC");
if ($result === false) {
    die("Database query failed: " . $conn->error);
}
$diseases = $result->fetch_all(MYSQLI_ASSOC);

// Get news
$news_result = $conn->query("SELECT * FROM medical_news ORDER BY published_date DESC");
$news = $news_result->fetch_all(MYSQLI_ASSOC);

// Get inventions
$inventions_result = $conn->query("SELECT * FROM inventions ORDER BY created_at DESC");
$inventions = $inventions_result ? $inventions_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Content - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #e63946;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #334155;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #334155;
            text-align: center;
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .sidebar-header p {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover);
            color: white;
        }

        .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: white;
            border-right: 4px solid var(--success-color);
        }

        .sidebar-menu i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: var(--transition);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--light-color);
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 0.375rem;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: #e2e8f0;
        }

        /* Content Container */
        .container {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark-color);
            font-size: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
        }

        .tab i {
            font-size: 1.1rem;
        }

        .tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .tab.active {
            background: var(--primary-color);
            color: white;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        /* Content Section */
        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-header h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-help {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Content Cards */
        .content-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
        }

        .content-card h3 {
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .content-card p {
            color: #64748b;
            margin-bottom: 1rem;
        }

        .content-card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* News/Invention Cards */
        .news-card-image {
            width: 100%;
            height: 200px;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
            background: #f8fafc;
        }

        .news-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .news-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            gap: 0.5rem;
            font-size: 2rem;
        }

        .news-image-placeholder i {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .news-image-placeholder small {
            font-size: 0.85rem;
            opacity: 0.9;
            text-align: center;
        }

        .news-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .news-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .news-meta i {
            color: var(--primary-color);
        }

        .news-excerpt {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0.75rem 0;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--card-shadow);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .modal-header h2 {
            color: #1e293b;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--danger-color);
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar-menu {
                display: none;
            }

            .sidebar.active .sidebar-menu {
                display: block;
            }

            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .content-card-actions {
                flex-direction: column;
            }

            .tabs {
                flex-direction: column;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .news-card-image {
                height: 150px;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class='sidebar'>
            <div class='sidebar-header'>
                <h2><i class='fas fa-heartbeat'></i> <?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Panel</p>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class='sidebar-menu'>
                <li><a href='dashboard.php'><i class='fas fa-th-large'></i> Dashboard</a></li>
                <li><a href='manage_doctors.php'><i class='fas fa-user-md'></i> Manage Doctors</a></li>
                <li><a href='manage_patients.php'><i class='fas fa-users'></i> Manage Patients</a></li>
                <li><a href='manage_cities.php'><i class='fas fa-city'></i> Manage Cities</a></li>
                <li><a href='manage_appointments.php'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php' class='active'><i class='fas fa-newspaper'></i> Content Management</a>
                </li>
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Manage Website Content</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-edit"></i> Manage Website Content</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="tabs">
                    <a href="?tab=diseases" class="tab <?php echo $tab == 'diseases' ? 'active' : ''; ?>">
                        <i class="fas fa-disease"></i> Diseases
                    </a>
                    <a href="?tab=news" class="tab <?php echo $tab == 'news' ? 'active' : ''; ?>">
                        <i class="fas fa-newspaper"></i> Medical News
                    </a>
                    <a href="?tab=inventions" class="tab <?php echo $tab == 'inventions' ? 'active' : ''; ?>">
                        <i class="fas fa-lightbulb"></i> Inventions
                    </a>
                </div>

                <!-- Diseases Tab -->
                <?php if ($tab == 'diseases'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Disease Information</h2>
                            <button class="btn btn-primary" onclick="openModal('diseaseModal')">
                                <i class="fas fa-plus"></i> Add Disease
                            </button>
                        </div>
                        <div class="d-flex gap-3 flex-column w-100">
                            <?php foreach ($diseases as $disease): ?>
                                <div class="content-card">
                                    <h3><?php echo htmlspecialchars($disease['disease_name']); ?></h3>
                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($disease['category']); ?></p>
                                    <p><?php echo htmlspecialchars(substr($disease['description'], 0, 200)); ?>...</p>
                                    <div class="content-card-actions">
                                        <button class="btn btn-primary btn-sm"
                                            onclick='openDiseaseViewModal(<?php echo json_encode($disease, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-secondary btn-sm"
                                            onclick='openDiseaseEditModal(<?php echo json_encode($disease, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this disease?');">
                                            <input type="hidden" name="disease_id"
                                                value="<?php echo htmlspecialchars($disease['id']); ?>">
                                            <button type="submit" name="delete_disease" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Disease Modal -->
                <div id="diseaseModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Add New Disease</h2>
                            <button class="modal-close" onclick="closeModal('diseaseModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Disease Name</label>
                                    <input type="text" name="disease_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="category" class="form-control"
                                        placeholder="e.g., Metabolic" required>
                                </div>
                                <div class="form-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Symptoms</label>
                                    <textarea name="symptoms" class="form-control" required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Prevention</label>
                                    <textarea name="prevention" class="form-control" required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Cure/Treatment</label>
                                    <textarea name="cure" class="form-control" required></textarea>
                                </div>
                            </div>
                            <button type="submit" name="add_disease" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-plus"></i> Add Disease
                            </button>
                        </form>
                    </div>
                </div>

                <!-- View Disease Modal -->
                <div id="viewDiseaseModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Disease Details</h2>
                            <button class="modal-close" onclick="closeModal('viewDiseaseModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="view-disease-content">
                            <h3 id="view-disease-name"></h3>
                            <p><strong>Category:</strong> <span id="view-disease-category"></span></p>
                            <p><strong>Description:</strong> <span id="view-disease-description"></span></p>
                            <p><strong>Symptoms:</strong> <span id="view-disease-symptoms"></span></p>
                            <p><strong>Prevention:</strong> <span id="view-disease-prevention"></span></p>
                            <p><strong>Cure/Treatment:</strong> <span id="view-disease-cure"></span></p>
                        </div>
                    </div>
                </div>

                <!-- Edit Disease Modal -->
                <div id="editDiseaseModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Edit Disease</h2>
                            <button class="modal-close" onclick="closeModal('editDiseaseModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="disease_id" id="edit-disease-id">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Disease Name</label>
                                    <input type="text" name="disease_name" id="edit-disease-name" class="form-control"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="category" id="edit-disease-category" class="form-control"
                                        required>
                                </div>
                                <div class="form-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" id="edit-disease-description" class="form-control"
                                        required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Symptoms</label>
                                    <textarea name="symptoms" id="edit-disease-symptoms" class="form-control"
                                        required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Prevention</label>
                                    <textarea name="prevention" id="edit-disease-prevention" class="form-control"
                                        required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Cure/Treatment</label>
                                    <textarea name="cure" id="edit-disease-cure" class="form-control"
                                        required></textarea>
                                </div>
                            </div>
                            <button type="submit" name="update_disease" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-save"></i> Update Disease
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Medical News Tab -->
                <?php if ($tab == 'news'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Medical News</h2>
                            <button class="btn btn-primary" onclick="openModal('newsModal')">
                                <i class="fas fa-plus"></i> Add News
                            </button>
                        </div>
                        <div class="d-flex gap-3 flex-column w-100">
                            <?php foreach ($news as $item): ?>
                                <div class="content-card">
                                    <!-- News Image -->
                                    <div class="news-card-image">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                                alt="<?php echo htmlspecialchars($item['title']); ?>" class="news-img">
                                        <?php else: ?>
                                            <div class="news-image-placeholder">
                                                <i class="fas fa-newspaper"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="news-card-content">
                                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <div class="news-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars($item['category']); ?></span>
                                            <span><i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($item['author']); ?></span>
                                            <span><i class="far fa-calendar"></i>
                                                <?php echo htmlspecialchars(formatDate($item['published_date'])); ?></span>
                                        </div>
                                        <p class="news-excerpt">
                                            <?php echo htmlspecialchars(substr($item['content'], 0, 150)); ?>...
                                        </p>

                                        <div class="content-card-actions">
                                            <button class="btn btn-primary btn-sm"
                                                onclick='openViewModal(<?php echo json_encode($item, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>, "news")'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-secondary btn-sm"
                                                onclick='openEditModal(<?php echo json_encode($item, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>, "news")'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" enctype="multipart/form-data"
                                                onsubmit="return confirm('Are you sure you want to delete this news?');"
                                                style="display: inline;">
                                                <input type="hidden" name="news_id"
                                                    value="<?php echo htmlspecialchars($item['news_id']); ?>">
                                                <button type="submit" name="delete_news" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add News Modal -->
                <div id="newsModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Add Medical News</h2>
                            <button class="modal-close" onclick="closeModal('newsModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-control" required
                                        placeholder="Enter news title">
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <option value="Cardiology">Cardiology</option>
                                        <option value="Neurology">Neurology</option>
                                        <option value="Oncology">Oncology</option>
                                        <option value="Mental Health">Mental Health</option>
                                        <option value="Pediatrics">Pediatrics</option>
                                        <option value="Surgery">Surgery</option>
                                        <option value="Medical Research">Medical Research</option>
                                        <option value="Public Health">Public Health</option>
                                        <option value="Chronic Diseases">Chronic Diseases</option>
                                        <option value="Infectious Diseases">Infectious Diseases</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Nutrition">Nutrition</option>
                                        <option value="Medical Technology">Medical Technology</option>
                                        <option value="Health News">Health News</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Author</label>
                                    <input type="text" name="author" class="form-control" required
                                        placeholder="Dr. John Doe">
                                </div>
                                <div class="form-group full-width">
                                    <label>News Image</label>
                                    <input type="file" name="news_image" class="form-control" accept="image/*">
                                    <small class="form-help">Recommended size: 400x300px (JPEG, PNG, WebP)</small>
                                </div>
                                <div class="form-group full-width">
                                    <label>Content</label>
                                    <textarea name="content" class="form-control" rows="8" required
                                        placeholder="Write news content here..."></textarea>
                                </div>
                            </div>
                            <button type="submit" name="add_news" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-plus"></i> Add News
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Edit News Modal -->
                <div id="editNewsModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Edit News</h2>
                            <button class="modal-close" onclick="closeModal('editNewsModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="news_id" id="edit-news-id">
                            <input type="hidden" name="current_image" id="edit-current-image">

                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Current Image</label>
                                    <div id="edit-current-image-preview" style="margin-bottom: 1rem;">
                                        <img id="edit-news-image-preview" src="" alt=""
                                            style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px; display: none;">
                                        <div id="edit-news-image-placeholder"
                                            style="width: 200px; height: 150px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                            <i class="fas fa-image"></i> No Image
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group full-width">
                                    <label>Change Image</label>
                                    <input type="file" name="news_image" class="form-control" accept="image/*">
                                    <small class="form-help">Leave empty to keep current image</small>
                                </div>

                                <div class="form-group full-width">
                                    <label>Title</label>
                                    <input type="text" name="title" id="edit-news-title" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <option value="Cardiology">Cardiology</option>
                                        <option value="Neurology">Neurology</option>
                                        <option value="Oncology">Oncology</option>
                                        <option value="Mental Health">Mental Health</option>
                                        <option value="Pediatrics">Pediatrics</option>
                                        <option value="Surgery">Surgery</option>
                                        <option value="Medical Research">Medical Research</option>
                                        <option value="Public Health">Public Health</option>
                                        <option value="Chronic Diseases">Chronic Diseases</option>
                                        <option value="Infectious Diseases">Infectious Diseases</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Nutrition">Nutrition</option>
                                        <option value="Medical Technology">Medical Technology</option>
                                        <option value="Health News">Health News</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Author</label>
                                    <input type="text" name="author" id="edit-news-author" class="form-control"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label>Published Date</label>
                                    <input type="datetime-local" name="published_date" id="edit-news-date"
                                        class="form-control">
                                </div>

                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" id="edit-news-status" class="form-control">
                                        <option value="published">Published</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label>Content</label>
                                    <textarea name="content" id="edit-news-content" class="form-control" rows="8"
                                        required></textarea>
                                </div>
                            </div>

                            <button type="submit" name="update_news" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-save"></i> Update News
                            </button>
                        </form>
                    </div>
                </div>

                <!-- View News Modal -->
                <div id="viewNewsModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>News Details</h2>
                            <button class="modal-close" onclick="closeModal('viewNewsModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="view-news-content">
                            <div class="view-news-image">
                                <img id="view-news-image" src="" alt=""
                                    style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
                                <div id="view-news-image-placeholder"
                                    style="display: none; width: 100%; height: 200px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin-bottom: 1rem;">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            </div>
                            <h3 id="view-news-title" style="color: var(--dark-color); margin-bottom: 1rem;"></h3>
                            <div class="view-news-meta"
                                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                                <div>
                                    <strong>Category:</strong><br>
                                    <span id="view-news-category" style="color: #64748b;"></span>
                                </div>
                                <div>
                                    <strong>Author:</strong><br>
                                    <span id="view-news-author" style="color: #64748b;"></span>
                                </div>
                                <div>
                                    <strong>Published:</strong><br>
                                    <span id="view-news-date" style="color: #64748b;"></span>
                                </div>
                                <div>
                                    <strong>Status:</strong><br>
                                    <span id="view-news-status" style="color: #64748b;"></span>
                                </div>
                            </div>
                            <div>
                                <strong>Content:</strong>
                                <p id="view-news-content-text"
                                    style="color: #374151; line-height: 1.6; margin-top: 0.5rem; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventions Tab -->
                <?php if ($tab == 'inventions'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Medical Inventions</h2>
                            <button class="btn btn-primary" onclick="openModal('inventionModal')">
                                <i class="fas fa-plus"></i> Add Invention
                            </button>
                        </div>
                        <div class="d-flex gap-3 flex-column w-100">
                            <?php foreach ($inventions as $invention): ?>
                                <div class="content-card">
                                    <!-- Invention Image -->
                                    <div class="news-card-image">
                                        <?php if (!empty($invention['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($invention['image_url']); ?>"
                                                alt="<?php echo htmlspecialchars($invention['invention_name']); ?>"
                                                class="news-img">
                                        <?php else: ?>
                                            <div class="news-image-placeholder">
                                                <i class="fas fa-lightbulb"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="news-card-content">
                                        <h3><?php echo htmlspecialchars($invention['invention_name']); ?></h3>
                                        <div class="news-meta">
                                            <span><i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars($invention['category']); ?></span>
                                            <span><i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($invention['inventor']); ?></span>
                                        </div>
                                        <p class="news-excerpt">
                                            <?php echo htmlspecialchars(substr($invention['description'], 0, 150)); ?>...
                                        </p>
                                        <p style="color: #10b981; font-size: 0.9rem; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo htmlspecialchars(substr($invention['benefits'], 0, 80)); ?>...
                                        </p>

                                        <div class="content-card-actions">
                                            <button class="btn btn-primary btn-sm"
                                                onclick='openViewModal(<?php echo json_encode($invention, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>, "invention")'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-secondary btn-sm"
                                                onclick='openEditModal(<?php echo json_encode($invention, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>, "invention")'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" enctype="multipart/form-data"
                                                onsubmit="return confirm('Are you sure you want to delete this invention?');"
                                                style="display: inline;">
                                                <input type="hidden" name="invention_id"
                                                    value="<?php echo htmlspecialchars($invention['id']); ?>">
                                                <button type="submit" name="delete_invention" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Invention Modal -->
                <div id="inventionModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Add Medical Invention</h2>
                            <button class="modal-close" onclick="closeModal('inventionModal')"><i
                                    class="fas fa-times"></i></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Invention Name</label>
                                    <input type="text" name="invention_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="category" class="form-control" placeholder="e.g., Device"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Inventor</label>
                                    <input type="text" name="inventor" class="form-control"
                                        placeholder="e.g., Dr. Jane Smith" required>
                                </div>
                                <div class="form-group full-width">
                                    <label>Invention Image</label>
                                    <input type="file" name="invention_image" class="form-control" accept="image/*">
                                    <small class="form-help">Recommended size: 400x300px (JPEG, PNG, WebP)</small>
                                </div>
                                <div class="form-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Benefits</label>
                                    <textarea name="benefits" class="form-control" required></textarea>
                                </div>
                            </div>
                            <button type="submit" name="add_invention" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-plus"></i> Add Invention
                            </button>
                        </form>
                    </div>
                </div>

                <!-- View Invention Modal -->
                <div id="viewInventionModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Invention Details</h2>
                            <button class="modal-close" onclick="closeModal('viewInventionModal')"><i
                                    class="fas fa-times"></i></button>
                        </div>
                        <div id="view-invention-content">
                            <div id="view-invention-image-container" style="margin-bottom: 1.5rem;">
                                <img id="view-invention-image" src="" alt=""
                                    style="width: 100%; height: 250px; object-fit: cover; border-radius: 8px; display: none;">
                                <div id="view-invention-image-placeholder"
                                    style="width: 100%; height: 250px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-lightbulb" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                            <h3 id="view-invention-name"></h3>
                            <p><strong>Category:</strong> <span id="view-invention-category"></span></p>
                            <p><strong>Inventor:</strong> <span id="view-invention-inventor"></span></p>
                            <p><strong>Description:</strong> <span id="view-invention-description"></span></p>
                            <p><strong>Benefits:</strong> <span id="view-invention-benefits"
                                    style="color: #10b981; font-weight: 600;"></span></p>
                        </div>
                    </div>
                </div>

                <!-- Edit Invention Modal -->
                <div id="editInventionModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Edit Invention</h2>
                            <button class="modal-close" onclick="closeModal('editInventionModal')"><i
                                    class="fas fa-times"></i></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="invention_id" id="edit-invention-id">
                            <input type="hidden" name="current_image" id="edit-current-invention-image">

                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Current Image</label>
                                    <div id="edit-current-invention-image-preview" style="margin-bottom: 1rem;">
                                        <img id="edit-invention-image-preview" src="" alt=""
                                            style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px; display: none;">
                                        <div id="edit-invention-image-placeholder"
                                            style="width: 200px; height: 150px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: 2px dashed #059669; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                            <i class="fas fa-lightbulb" style="font-size: 2rem;"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group full-width">
                                    <label>Change Image</label>
                                    <input type="file" name="invention_image" class="form-control" accept="image/*">
                                    <small class="form-help">Leave empty to keep current image</small>
                                </div>

                                <div class="form-group">
                                    <label>Invention Name</label>
                                    <input type="text" name="invention_name" id="edit-invention-name"
                                        class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="text" name="category" id="edit-invention-category" class="form-control"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Inventor</label>
                                    <input type="text" name="inventor" id="edit-invention-inventor" class="form-control"
                                        required>
                                </div>
                                <div class="form-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" id="edit-invention-description" class="form-control"
                                        required></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Benefits</label>
                                    <textarea name="benefits" id="edit-invention-benefits" class="form-control"
                                        required></textarea>
                                </div>
                            </div>
                            <button type="submit" name="update_invention" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">
                                <i class="fas fa-save"></i> Update Invention
                            </button>
                        </form>
                    </div>
                </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Disease View Modal Handler
        function openDiseaseViewModal(disease) {
            document.getElementById('view-disease-name').textContent = disease.disease_name || 'No Name';
            document.getElementById('view-disease-category').textContent = disease.category || 'No Category';
            document.getElementById('view-disease-description').textContent = disease.description || 'No Description';
            document.getElementById('view-disease-symptoms').textContent = disease.symptoms || 'No Symptoms';
            document.getElementById('view-disease-prevention').textContent = disease.prevention || 'No Prevention';
            document.getElementById('view-disease-cure').textContent = disease.cure || 'No Cure';
            openModal('viewDiseaseModal');
        }

        // Disease Edit Modal Handler
        function openDiseaseEditModal(disease) {
            document.getElementById('edit-disease-id').value = disease.id;
            document.getElementById('edit-disease-name').value = disease.disease_name || '';
            document.getElementById('edit-disease-category').value = disease.category || '';
            document.getElementById('edit-disease-description').value = disease.description || '';
            document.getElementById('edit-disease-symptoms').value = disease.symptoms || '';
            document.getElementById('edit-disease-prevention').value = disease.prevention || '';
            document.getElementById('edit-disease-cure').value = disease.cure || '';
            openModal('editDiseaseModal');
        }

        // News View Modal Handler
        function openViewModalNews(item) {
            document.getElementById('view-news-title').textContent = item.title;
            document.getElementById('view-news-category').textContent = item.category;
            document.getElementById('view-news-author').textContent = item.author;
            document.getElementById('view-news-date').textContent = new Date(item.published_date).toLocaleDateString();
            document.getElementById('view-news-status').textContent = item.status;
            document.getElementById('view-news-content-text').textContent = item.content;
            openModal('viewNewsModal');
        }

        // News Edit Modal Handler
        function openEditModalNews(item) {
            document.getElementById('edit-news-id').value = item.news_id;
            document.getElementById('edit-news-title').value = item.title;
            document.getElementById('edit-news-category').value = item.category;
            document.getElementById('edit-news-author').value = item.author;
            document.getElementById('edit-news-content').value = item.content;
            document.getElementById('edit-news-date').value = item.published_date ? new Date(item.published_date).toISOString().slice(0, 16) : '';
            document.getElementById('edit-news-status').value = item.status || 'published';
            openModal('editNewsModal');
        }

        // Invention View Modal Handler
        function openViewModalInvention(item) {
            document.getElementById('view-invention-name').textContent = item.invention_name;
            document.getElementById('view-invention-category').textContent = item.category;
            document.getElementById('view-invention-inventor').textContent = item.inventor;
            document.getElementById('view-invention-description').textContent = item.description;
            document.getElementById('view-invention-benefits').textContent = item.benefits;

            // Handle image display
            if (item.image_url && item.image_url.trim() !== '') {
                const img = document.getElementById('view-invention-image');
                img.src = '/' + item.image_url;
                img.style.display = 'block';
                document.getElementById('view-invention-image-placeholder').style.display = 'none';
            } else {
                document.getElementById('view-invention-image').style.display = 'none';
                document.getElementById('view-invention-image-placeholder').style.display = 'flex';
            }
            openModal('viewInventionModal');
        }

        // Invention Edit Modal Handler
        function openEditModalInvention(item) {
            document.getElementById('edit-invention-id').value = item.id;
            document.getElementById('edit-invention-name').value = item.invention_name;
            document.getElementById('edit-invention-category').value = item.category;
            document.getElementById('edit-invention-inventor').value = item.inventor;
            document.getElementById('edit-invention-description').value = item.description;
            document.getElementById('edit-invention-benefits').value = item.benefits;
            document.getElementById('edit-current-invention-image').value = item.image_url || '';

            // Handle current image preview
            if (item.image_url && item.image_url.trim() !== '') {
                const img = document.getElementById('edit-invention-image-preview');
                img.src = '/' + item.image_url;
                img.style.display = 'block';
                document.getElementById('edit-invention-image-placeholder').style.display = 'none';
            } else {
                document.getElementById('edit-invention-image-preview').style.display = 'none';
                document.getElementById('edit-invention-image-placeholder').style.display = 'flex';
            }
            openModal('editInventionModal');
        }

        function openViewModal(item, type) {
            if (type === 'news') {
                openViewModalNews(item);
            } else if (type === 'invention') {
                openViewModalInvention(item);
            }
        }

        function openEditModal(item, type) {
            if (type === 'news') {
                openEditModalNews(item);
            } else if (type === 'invention') {
                openEditModalInvention(item);
            }
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>fv
    <?php ob_end_flush(); ?>

</html>