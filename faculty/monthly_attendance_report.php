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
$month = $_GET["month"] ?? "";

// Validate subject and month
if (
    $subject === "" ||
    !preg_match('/^\d{4}-\d{2}$/', $month) ||
    !checkdate(
        (int) substr($month, 5, 2),
        1,
        (int) substr($month, 0, 4)
    )
) {
    http_response_code(400);
    exit("Invalid subject or month.");
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

// Fetch monthly attendance
$startDate = $month . "-01";
$endDate = date("Y-m-t", strtotime($startDate));

$sql = "
    SELECT
        s.id AS student_id,
        s.name,
        s.roll_no,
        a.attendance_date,
        a.status
    FROM attendance a
    INNER JOIN students s ON s.id = a.student_id
    WHERE a.subject = ?
    AND a.attendance_date BETWEEN ? AND ?
    ORDER BY s.roll_no, a.attendance_date
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $subject, $startDate, $endDate);
$stmt->execute();

$result = $stmt->get_result();

$students = [];
$dates = [];

while ($row = $result->fetch_assoc()) {

    $studentId = $row["student_id"];
    $date = $row["attendance_date"];

    if (!isset($students[$studentId])) {
        $students[$studentId] = [
            "name" => $row["name"],
            "roll_no" => $row["roll_no"],
            "records" => []
        ];
    }

    $students[$studentId]["records"][$date] = $row["status"];
    $dates[$date] = true;
}

$stmt->close();
$conn->close();

$dates = array_keys($dates);
sort($dates);

// Escape HTML output
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$displayMonth = date("F Y", strtotime($startDate));

$totalStudents = count($students);
$totalPresent = 0;
$totalAbsent = 0;
$overallRecords = 0;
$lowAttendance = 0;

// Calculate monthly totals
foreach ($students as $student) {

    foreach ($student["records"] as $status) {

        if ($status === "Present") {
            $totalPresent++;
            $overallRecords++;
        } elseif ($status === "Absent") {
            $totalAbsent++;
            $overallRecords++;
        }
    }

    $studentPresent = 0;
    $studentAbsent = 0;

    foreach ($student["records"] as $status) {

        if ($status === "Present") {
            $studentPresent++;
        } elseif ($status === "Absent") {
            $studentAbsent++;
        }
    }

    $studentTotal = $studentPresent + $studentAbsent;

    $studentPercentage = $studentTotal > 0
        ? ($studentPresent / $studentTotal) * 100
        : 0;

    if ($studentTotal > 0 && $studentPercentage < 75) {
        $lowAttendance++;
    }
}

$overallPercentage = $overallRecords > 0
    ? round(($totalPresent / $overallRecords) * 100, 2)
    : 0;

$filename = "Apollo_Monthly_Attendance_" . $month . ".xls";

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
            size: A3 landscape;
            margin: 10mm;
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
            margin: 12px 0;
            border-collapse: collapse;
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
            font-size: 19px;
            font-weight: bold;
            color: #1e3a8a;
            padding-top: 6px;
        }

        .present-summary {
            color: #15803d;
        }

        .absent-summary {
            color: #b91c1c;
        }

        .low-summary {
            color: #b91c1c;
        }

        /* ATTENDANCE TABLE */

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attendance-table th {
            background: #1e3a8a;
            color: #ffffff;
            padding: 9px 5px;
            border: 1px solid #1e3a8a;
            text-align: center;
            font-size: 10px;
            white-space: nowrap;
        }

        .attendance-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 5px;
            text-align: center;
            font-size: 10px;
        }

        .attendance-table tbody tr:nth-child(even) {
            background: #eff6ff;
        }

        .student-name {
            text-align: left !important;
            min-width: 130px;
        }

        .roll-number {
            min-width: 85px;
        }

        /* DAILY STATUS */

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

        .no-record {
            color: #9ca3af;
        }

        /* TOTAL COLUMNS */

        .total-column {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: bold;
        }

        /* ATTENDANCE PERCENTAGE */

        .percentage-good {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
        }

        .percentage-low {
            background: #fee2e2;
            color: #991b1b;
            font-weight: bold;
        }

        /* LEGEND */

        .legend {
            margin-top: 18px;
            padding: 12px;
            background: #eff6ff;
            border: 1px solid #cbd5e1;
            font-size: 11px;
        }

        .legend span {
            margin-right: 22px;
            font-weight: bold;
        }

        /* SUMMARY TEXT */

        .summary-text {
            margin-top: 12px;
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
        }

        /* SIGNATURE */

        .signature-section {
            width: 100%;
            margin-top: 45px;
            border-collapse: collapse;
        }

        .signature-section td {
            width: 50%;
            text-align: center;
            padding-top: 30px;
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
            margin-top: 20px;
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
            MONTHLY STUDENT ATTENDANCE REPORT
        </div>

        <!-- REPORT INFORMATION -->

        <table class="report-info">

            <tr>

                <td>
                    <strong>Subject:</strong>
                    <?= e($subject) ?>
                </td>

                <td>
                    <strong>Month:</strong>
                    <?= e($displayMonth) ?>
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

        <!-- MONTHLY SUMMARY -->

        <table class="summary-table">

            <tr>

                <td>
                    <div class="summary-label">TOTAL STUDENTS</div>
                    <div class="summary-value">
                        <?= $totalStudents ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">TOTAL PRESENT</div>
                    <div class="summary-value present-summary">
                        <?= $totalPresent ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">TOTAL ABSENT</div>
                    <div class="summary-value absent-summary">
                        <?= $totalAbsent ?>
                    </div>
                </td>

                <td>
                    <div class="summary-label">OVERALL ATTENDANCE</div>
                    <div class="summary-value">
                        <?= $overallPercentage ?>%
                    </div>
                </td>

            </tr>

        </table>

        <!-- ATTENDANCE TABLE -->

        <table class="attendance-table">

            <thead>

                <tr>

                    <th>Sr. No.</th>

                    <th>Student Name</th>

                    <th>Roll Number</th>

                    <?php foreach ($dates as $date): ?>

                        <th>
                            <?= date("d", strtotime($date)) ?>
                        </th>

                    <?php endforeach; ?>

                    <th>Total Present</th>

                    <th>Total Absent</th>

                    <th>Attendance %</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($totalStudents > 0): ?>

                    <?php $serial = 1; ?>

                    <?php foreach ($students as $student): ?>

                        <?php

                        $present = 0;
                        $absent = 0;

                        ?>

                        <tr>

                            <td><?= $serial++ ?></td>

                            <td class="student-name">
                                <?= e($student["name"]) ?>
                            </td>

                            <td class="roll-number">
                                <?= e($student["roll_no"]) ?>
                            </td>

                            <?php foreach ($dates as $date): ?>

                                <?php

                                $status = $student["records"][$date] ?? "N/A";

                                ?>

                                <td class="<?=
                                            $status === "Present"
                                                ? "present"
                                                : ($status === "Absent"
                                                    ? "absent"
                                                    : "no-record")
                                            ?>">

                                    <?php

                                    if ($status === "Present") {
                                        echo "P";
                                        $present++;
                                    } elseif ($status === "Absent") {
                                        echo "A";
                                        $absent++;
                                    } else {
                                        echo "-";
                                    }

                                    ?>

                                </td>

                            <?php endforeach; ?>

                            <?php

                            $total = $present + $absent;

                            $percentage = $total > 0
                                ? round(($present / $total) * 100, 2)
                                : 0;

                            $percentageClass = $percentage < 75
                                ? "percentage-low"
                                : "percentage-good";

                            ?>

                            <td class="total-column">
                                <?= $present ?>
                            </td>

                            <td class="total-column">
                                <?= $absent ?>
                            </td>

                            <td class="<?= $percentageClass ?>">
                                <?= number_format($percentage, 2) ?>%
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="<?= count($dates) + 6 ?>">
                            No attendance records found for this month.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

        <!-- LEGEND -->

        <div class="legend">

            <span class="present">P = Present</span>

            <span class="absent">A = Absent</span>

            <span class="no-record">- = No Record</span>

            <span class="percentage-low">
                Below 75% = Low Attendance
            </span>

            <span class="percentage-good">
                75% or Above = Good Attendance
            </span>

        </div>

        <div class="summary-text">

            Total Students: <?= $totalStudents ?>

            &nbsp; | &nbsp;

            Low Attendance Students: <?= $lowAttendance ?>

            &nbsp; | &nbsp;

            Total Attendance Records: <?= $overallRecords ?>

        </div>

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
            Monthly Student Attendance Report |
            This is a computer-generated attendance report.
        </div>

    </div>

</body>

</html>