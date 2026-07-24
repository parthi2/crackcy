<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Path to PHPMailer files
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function sendQuotationEmails($orderNo, $customerDetails, $orderedItems, $totalAmount) {
    // --- GMAIL CONFIGURATION ---
    $smtpHost     = 'smtp.gmail.com';
    $smtpUsername = 'YOUR_GMAIL_ADDRESS@gmail.com'; // Enter your Gmail
    $smtpPassword = 'YOUR_16_DIGIT_APP_PASSWORD';  // Enter your App Password (no spaces)
    $smtpPort     = 587;                           // TLS port
    $adminEmail   = 'YOUR_GMAIL_ADDRESS@gmail.com'; // Where you want to receive order notifications
    $siteName     = 'RetailStore Crackers';

    // Build Items Table HTML
    $itemsTableHtml = '
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif;">
        <thead style="background-color:#1E293B; color:#ffffff;">
            <tr>
                <th align="left">Product Name</th>
                <th align="center">Price</th>
                <th align="center">Qty</th>
                <th align="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($orderedItems as $item) {
        $itemsTableHtml .= '
            <tr>
                <td>' . htmlspecialchars($item['name']) . '</td>
                <td align="center">₹' . number_format($item['price'], 2) . '</td>
                <td align="center">' . $item['qty'] . '</td>
                <td align="right">₹' . number_format($item['subtotal'], 2) . '</td>
            </tr>';
    }

    $itemsTableHtml .= '
            <tr style="background-color:#F8FAFC;">
                <td colspan="3" align="right"><strong>Estimated Total Amount:</strong></td>
                <td align="right" style="color:#E53935; font-weight:bold; font-size:16px;">₹' . number_format($totalAmount, 2) . '</td>
            </tr>
        </tbody>
    </table>';

    // -------------------------------------------------------------
    // 1. MAIL TO ADMIN (New Quote Alert)
    // -------------------------------------------------------------
    try {
        $adminMail = new PHPMailer(true);
        $adminMail->isSMTP();
        $adminMail->Host       = $smtpHost;
        $adminMail->SMTPAuth   = true;
        $adminMail->Username   = $smtpUsername;
        $adminMail->Password   = $smtpPassword;
        $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $adminMail->Port       = $smtpPort;

        $adminMail->setFrom($smtpUsername, $siteName);
        $adminMail->addAddress($adminEmail);
        $adminMail->isHTML(true);
        $adminMail->Subject = "🚨 New Quotation Received - #" . $orderNo;

        $adminMail->Body = "
        <div style='font-family:Arial, sans-serif; max-width:650px; margin:auto; border:1px solid #E2E8F0; padding:20px; border-radius:10px;'>
            <h2 style='color:#E53935; border-bottom:2px solid #E53935; padding-bottom:10px;'>New Quotation Order Received!</h2>
            <p><strong>Order Number:</strong> " . htmlspecialchars($orderNo) . "</p>
            
            <h3 style='background:#F1F5F9; padding:8px;'>Customer Information</h3>
            <p>
                <strong>Name:</strong> " . htmlspecialchars($customerDetails['name']) . "<br>
                <strong>Phone:</strong> " . htmlspecialchars($customerDetails['phone']) . "<br>
                <strong>Email:</strong> " . htmlspecialchars($customerDetails['email'] ?: 'N/A') . "<br>
                <strong>Delivery Address:</strong> " . htmlspecialchars($customerDetails['address']) . ", " . htmlspecialchars($customerDetails['city']) . ", " . htmlspecialchars($customerDetails['state']) . " - " . htmlspecialchars($customerDetails['pincode']) . "
            </p>

            <h3 style='background:#F1F5F9; padding:8px;'>Requested Products</h3>
            " . $itemsTableHtml . "

            <p style='margin-top:20px; color:#64748B; font-size:12px;'>This notification was generated automatically by " . $siteName . ".</p>
        </div>";

        $adminMail->send();
    } catch (Exception $e) {
        error_log("Admin Mail Error: " . $adminMail->ErrorInfo);
    }

    // -------------------------------------------------------------
    // 2. MAIL TO CUSTOMER (Confirmation Email)
    // -------------------------------------------------------------
    if (!empty($customerDetails['email']) && filter_var($customerDetails['email'], FILTER_VALIDATE_EMAIL)) {
        try {
            $custMail = new PHPMailer(true);
            $custMail->isSMTP();
            $custMail->Host       = $smtpHost;
            $custMail->SMTPAuth   = true;
            $custMail->Username   = $smtpUsername;
            $custMail->Password   = $smtpPassword;
            $custMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $custMail->Port       = $smtpPort;

            $custMail->setFrom($smtpUsername, $siteName);
            $custMail->addAddress($customerDetails['email'], $customerDetails['name']);
            $custMail->isHTML(true);
            $custMail->Subject = "Quotation Confirmation - #" . $orderNo;

            $custMail->Body = "
            <div style='font-family:Arial, sans-serif; max-width:650px; margin:auto; border:1px solid #E2E8F0; padding:20px; border-radius:10px;'>
                <h2 style='color:#E53935; border-bottom:2px solid #E53935; padding-bottom:10px;'>Thank You for Your Quotation Request!</h2>
                <p>Dear <strong>" . htmlspecialchars($customerDetails['name']) . "</strong>,</p>
                <p>We have successfully received your quotation request <strong>#" . htmlspecialchars($orderNo) . "</strong>. Our team is reviewing the availability and will contact you shortly.</p>

                <h3 style='background:#F1F5F9; padding:8px;'>Quotation Summary</h3>
                " . $itemsTableHtml . "

                <h3 style='background:#F1F5F9; padding:8px; margin-top:20px;'>Shipping Address</h3>
                <p>
                    " . htmlspecialchars($customerDetails['address']) . "<br>
                    " . htmlspecialchars($customerDetails['city']) . ", " . htmlspecialchars($customerDetails['state']) . " - " . htmlspecialchars($customerDetails['pincode']) . "<br>
                    <strong>Mobile:</strong> " . htmlspecialchars($customerDetails['phone']) . "
                </p>

                <p style='margin-top:20px;'>If you have any questions, feel free to reply directly to this email.</p>
                <hr style='border:none; border-top:1px solid #E2E8F0;'>
                <p style='color:#64748B; font-size:12px; text-align:center;'>Regards,<br><strong>" . $siteName . " Team</strong></p>
            </div>";

            $custMail->send();
        } catch (Exception $e) {
            error_log("Customer Mail Error: " . $custMail->ErrorInfo);
        }
    }
}