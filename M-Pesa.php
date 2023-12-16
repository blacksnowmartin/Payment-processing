<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    // User is not logged in, handle accordingly (e.g., redirect to login page)
    header("Location: Login.php"); // Replace with your login page URL
    exit();
}

// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Grocify";

// M-PESA Sandbox credentials
$consumerKey = 'YDaxyUAswYdVCPwijYzGFimFZASppG0d';
$consumerSecret = '515kitqIKKdy9RyV';
$shortcode = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';

try {
    // Create a connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get the user's phone number and purchase total
    $query = "SELECT phone_number, purchase_total FROM Users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $phoneNumber = +$row['phone_number']; // Use the retrieved phone number from the database
        $purchaseTotal = $row['purchase_total'];

        // Close the database connection
        $stmt->close();
        $conn->close();

        // Perform the Lipa Na M-PESA transaction
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        $amount = $purchaseTotal * 1; // Convert amount to cents

        // Create the request payload
        $payload = array(
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => 'https://example.com/callback', // Replace with your dummy or placeholder callback URL
            'AccountReference' => 'Grocify Purchase',
            'TransactionDesc' => 'Grocify Purchase'
        );

        // Convert payload to JSON
        $payloadJson = json_encode($payload);

        // Make the API request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . generateAccessToken()));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        $response = curl_exec($ch);
        curl_close($ch);

        // Process the response
        $responseData = json_decode($response, true);

        // Print the response data for debugging


    } else {
        // User not found in the database, handle accordingly
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Function to generate M-PESA access token
function generateAccessToken()
{
    global $consumerKey, $consumerSecret;

    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if (isset($responseData['access_token'])) {
        return $responseData['access_token'];
    } else {
        throw new Exception('Invalid access token response: ' . $response);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transaction Box Example</title>
    <link rel="stylesheet" type="text/css" href="Mpesa.css">
</head>
<body>
<div id="transaction-box">
    <p id="transaction-message">
        <?php
        if ($responseData !== null) {
            // Process the response data
            if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                // Transaction initiated successfully
                $transactionID = $responseData['CheckoutRequestID'];
                // Store the transaction ID in the database or session for future reference

                // Handle the transaction on the user's mobile phone
                // The user will receive a message and be prompted to enter their M-PESA PIN to authorize the payment
                // You can display a success message or redirect to a success page here
                echo "Transaction initiated successfully. Please follow the instructions on your mobile phone to authorize the payment.";
            } else {
                // Transaction initiation failed
                throw new Exception('Error initiating M-PESA transaction: ' . $responseData['errorMessage']);
            }
        } else {
            // Invalid response data
            echo "Error: Invalid response data.";
        }
        ?>
    </p>
</div>
</body>
</html>
