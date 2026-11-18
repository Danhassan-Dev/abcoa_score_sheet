<?php
declare(strict_types=1);

/**
 * Returns dashboard figures and recent records visible to the signed-in user.
 * Administrators see institution-wide data; staff see only their own uploads
 * and activity. Department count is an institution-wide reference figure.
 */
function get_dashboard_data(PDO $pdo, array $user): array
{
    $isAdmin = ($user['role'] ?? '') === 'admin';

    if ($isAdmin) {
        $statsSql = "SELECT
            COUNT(*) AS total_documents,
            COALESCE(SUM(verification_status = 'verified'), 0) AS verified_documents,
            COALESCE(SUM(verification_status = 'pending'), 0) AS pending_documents,
            COALESCE(SUM(verification_status = 'rejected'), 0) AS rejected_documents
            FROM score_sheets";
        $stats = $pdo->query($statsSql)->fetch();
    } else {
        $statsSql = "SELECT
            COUNT(*) AS total_documents,
            COALESCE(SUM(verification_status = 'verified'), 0) AS verified_documents,
            COALESCE(SUM(verification_status = 'pending'), 0) AS pending_documents,
            COALESCE(SUM(verification_status = 'rejected'), 0) AS rejected_documents
            FROM score_sheets WHERE uploaded_by = :user_id";
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute(['user_id' => (int)$user['id']]);
        $stats = $statsStmt->fetch();
    }

    $departmentCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM departments WHERE status = 'active'"
    )->fetchColumn();

    $recentSql = "SELECT
        ss.id,
        ss.original_filename,
        ss.verification_status,
        ss.created_at,
        c.course_code,
        c.course_title,
        ac.session_name,
        u.full_name AS uploader_name
        FROM score_sheets ss
        INNER JOIN courses c ON c.id = ss.course_id
        INNER JOIN academic_sessions ac ON ac.id = ss.academic_session_id
        INNER JOIN users u ON u.id = ss.uploaded_by";

    if ($isAdmin) {
        $recentStmt = $pdo->query($recentSql . ' ORDER BY ss.created_at DESC, ss.id DESC LIMIT 5');
    } else {
        $recentStmt = $pdo->prepare(
            $recentSql . ' WHERE ss.uploaded_by = :user_id ORDER BY ss.created_at DESC, ss.id DESC LIMIT 5'
        );
        $recentStmt->execute(['user_id' => (int)$user['id']]);
    }
    $recentUploads = $recentStmt->fetchAll();

    if ($isAdmin) {
        $activityStmt = $pdo->query(
            "SELECT al.action, al.details, al.created_at, u.full_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC, al.id DESC LIMIT 5"
        );
    } else {
        $activityStmt = $pdo->prepare(
            "SELECT al.action, al.details, al.created_at, u.full_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.user_id = :user_id
             ORDER BY al.created_at DESC, al.id DESC LIMIT 5"
        );
        $activityStmt->execute(['user_id' => (int)$user['id']]);
    }
    $recentActivity = $activityStmt->fetchAll();

    return [
        'total_documents' => (int)($stats['total_documents'] ?? 0),
        'verified_documents' => (int)($stats['verified_documents'] ?? 0),
        'pending_documents' => (int)($stats['pending_documents'] ?? 0),
        'rejected_documents' => (int)($stats['rejected_documents'] ?? 0),
        'department_count' => $departmentCount,
        'recent_uploads' => $recentUploads,
        'recent_activity' => $recentActivity,
    ];
}
