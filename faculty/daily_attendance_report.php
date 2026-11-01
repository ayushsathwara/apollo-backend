<?php

session_name("APOLLO_FACULTY_SESSION");
session_start();

include "../db.php";

if (!isset($_SESSION["faculty_id"])) {
    http_response_code(401);
    exit("Faculty not logged in.");
}

$faculty_id = (int) $_SESSION["faculty_id"];

$subject = trim($_GET["subject"] ?? "");
$date = $_GET["date"] ?? "";

$dateObject = DateTime::createFromFormat("!Y-m-d", $date);

if (
    $subject === "" ||
    !$dateObject ||
    $dateObject->format("Y-m-d") !== $date
) {
    http_response_code(400);
    exit("Invalid subject or date.");
}

// Verify faculty subject permission
$permission = $conn->prepare("
    SELECT id
    FROM faculty_subjects
    WHERE faculty_id = ? AND subject = ?
");

$permission->bind_param("is", $faculty_id, $subject);
$permission->execute();

if ($permission->get_result()->num_rows === 0) {
    http_response_code(403);
    exit("You are not authorized for this subject.");
}

$permission->close();

// Fetch attendance
$sql = "
    SELECT
        s.name,
        s.roll_no,
        a.subject,
        a.attendance_date,
        a.status
    FROM attendance a
    INNER JOIN students s ON s.id = a.student_id
    WHERE a.subject = ?
    AND a.attendance_date = ?
    ORDER BY s.roll_no
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $subject, $date);
$stmt->execute();

$result = $stmt->get_result();

$rows = [];
$totalPresent = 0;
$totalAbsent = 0;

while ($row = $result->fetch_assoc()) {

    $rows[] = $row;

    if ($row["status"] === "Present") {
        $totalPresent++;
    } else {
        $totalAbsent++;
    }
}

$stmt->close();
$conn->close();

// Escape HTML output safely
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$displayDate = date("d-m-Y", strtotime($date));

$totalStudents = count($rows);

$attendancePercentage = $totalStudents > 0
    ? round(($totalPresent / $totalStudents) * 100, 2)
    : 0;

$filename = "Apollo_Daily_Attendance_" . $date . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-store, no-cache, must-revalidate");

?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #ffffff;
        }

        .report-container {
            width: 100%;
        }

        /* COLLEGE HEADER */

        .college-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #ffffff;
            background: #1e3a8a;
            padding: 18px;
        }

        .college-subtitle {
            text-align: center;
            font-size: 11px;
            color: #ffffff;
            background: #2563eb;
            padding: 7px;
        }

        /* REPORT TITLE */

        .report-title {
            text-align: center;
            font-size: 19px;
            font-weight: bold;
            color: #1e3a8a;
            padding: 18px 5px 10px;
        }

        /* REPORT INFORMATION */

        .report-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .report-info td {
            padding: 12px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            background: #eff6ff;
        }

        .report-info strong {
            color: #1e3a8a;
        }

        /* SUMMARY */

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .summary-table td {
            width: 25%;
            text-align: center;
            padding: 13px 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .summary-label {
            font-size: 11px;
            color: #64748b;
            font-weight: bold;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            padding-top: 5px;
        }

        .present-summary {
            color: #15803d;
        }

        .absent-summary {
            color: #b91c1c;
        }

        /* ATTENDANCE TABLE */

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 20px;
        }

        .attendance-table th {
            background: #1e3a8a;
            color: #ffffff;
            padding: 12px 7px;
            border: 1px solid #1e3a8a;
            text-align: center;
            font-size: 12px;
        }

        .attendance-table td {
            border: 1px solid #cbd5e1;
            padding: 11px 7px;
            text-align: center;
            font-size: 12px;
        }

        .attendance-table tbody tr:nth-child(even) {
            background: #eff6ff;
        }

        .attendance-table td:nth-child(2) {
            text-align: left;
        }

        /* STATUS */

        .present {
            color: #166534;
            background: #dcfce7;
            font-weight: bold;
        }

        .absent {
            color: #991b1b;
            background: #fee2e2;
            font-weight: bold;
        }

        /* TABLE FOOTER */

        .table-footer td {
            background: #dbeafe;
            font-weight: bold;
            color: #1e3a8a;
            padding: 12px;
        }

        /* SIGNATURE */

        .signature-section {
            width: 100%;
            margin-top: 55px;
            border-collapse: collapse;
        }

        .signature-section td {
            width: 50%;
            text-align: center;
            padding-top: 35px;
            font-size: 12px;
        }

        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 8px;
            margin: 0 35px;
        }

        /* FOOTER */

        .footer {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 25px;
            padding: 10px;
            border-top: 1px solid #cbd5e1;
        }
    </style>

</head>

<body>

    <div class="report-container">

        <!-- COLLEGE HEADER -->

        <div class="college-title">
            APOLLO INSTITUTE OF COMPUTER APPLICATION
        </div>

        <div class="college-subtitle">
            BACHELOR OF COMPUTER APPLICATIONS
        </div>

        <!-- REPORT TITLE -->

        <div class="report-title">
            DAILY STUDENT ATTENDANCE REPORT
        </div>

        <!-- REPORT DETAILS -->

        <table class="report-info">

            <tr>
                <td>
                    <strong>Subject:</strong>
                    <?= e($subject) ?>
                </td>

                <td>
                    <strong>Report Date:</strong>
                    <?= e($displayDate) ?>
                </td>

                <td>
                    <strong>Academic Course:</strong>
                    BCA
                </td>

                <td>
                    <strong>Generated On:</strong>
                    <?= date("d-m-Y") ?>
                </td>
            </tr>

        </table>

        <!-- SUMMARY -->

        <table class="summary-table">

            <tr>

                <td>
                    <div class="summary-label">TOTAL STUDENTS</div>
                    <div class="summary-value">
                        <?= $totalStudents ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">PRESENT</div>
                    <div class="summary-value present-summary">
                        <?= $totalPresent ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">ABSENT</div>
                    <div class="summary-value absent-summary">
                        <?= $totalAbsent ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">ATTENDANCE</div>
                    <div class="summary-value">
                        <?= $attendancePercentage ?>%
                    </div>
                </td>

            </tr>

        </table>

        <!-- ATTENDANCE TABLE -->

        <table class="attendance-table">

            <colgroup>
                <col style="width: 8%">
                <col style="width: 32%">
                <col style="width: 18%">
                <col style="width: 22%">
                <col style="width: 20%">
            </colgroup>

            <thead>

                <tr>
                    <th>Sr. No.</th>
                    <th>Student Name</th>
                    <th>Roll Number</th>
                    <th>Subject</th>
                    <th>Attendance Status</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($totalStudents > 0): ?>

                    <?php foreach ($rows as $index => $row): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td><?= e($row["name"]) ?></td>

                            <td><?= e($row["roll_no"]) ?></td>

                            <td><?= e($row["subject"]) ?></td>

                            <td class="<?= $row["status"] === "Present" ? "present" : "absent" ?>">
                                <?= e($row["status"]) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            No attendance records found for this date.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

            <tfoot>

                <tr class="table-footer">
                    <td colspan="5">
                        Total Attendance Records: <?= $totalStudents ?>
                    </td>
                </tr>

            </tfoot>

        </table>

        <!-- SIGNATURE SECTION -->

        <table class="signature-section">

            <tr>

                <td>
                    <div class="signature-line">
                        Faculty Signature
                    </div>
                </td>

                <td>
                    <div class="signature-line">
                        HOD / Authorized Signature
                    </div>
                </td>

            </tr>

        </table>

        <!-- FOOTER -->

        <div class="footer">
            Apollo Institute of Computer Application |
            Daily Student Attendance Report |
            This is a computer-generated attendance report.
        </div>

    </div>

</body>

</html>