<?php
function bad_req($error) {
    header('HTTP/1.1 400 Bad Request');
    echo($error);
    exit;
}

// Prüfe HTTP Method
if ($_SERVER["REQUEST_METHOD"] != "POST")
    bad_req();

$reqJSON = json_decode(file_get_contents('php://input'), true);

// Prüfe Vorhandensein der Variablen
$vars = array('firma', 'vorname', 'name', 'strasse', 'hausnr', 'plz', 'ort', 'email', 'email2', 'gebdatum', 'art', 'beitrag');
foreach ($vars as $var)
    if (!isset($reqJSON[$var]))
        bad_req("Variable not set: ".$var);

// Honeypot
if ($reqJSON['email2'] != '')
    bad_req("Gotcha!");

// Firma nur fördernd
if ($reqJSON['firma'] != '' && $reqJSON['art'] != 'fördernd')
    bad_req("Company can not be an orderly member.");

// Checking email
if (!filter_var($reqJSON['email'], FILTER_VALIDATE_EMAIL)) {
    bad_req("Email is invalid.");
}

// Prüfe Mitgliedsart und Beitragshöhe
$beitrag = bcmul(str_replace(',', '.', $reqJSON['beitrag']), 12, 2);
if ($reqJSON['art'] == 'fördernd')
    if (bccomp($beitrag, '12') < 0)
        bad_req("Fee is too low.");
else if ($reqJSON['art'] == 'ordentlich')
    if (bccomp($beitrag, '15') < 0)
        bad_req("Fee is too low.");
else
    bad_req("Unknown type of member.");
$beitrag_komma = str_replace('.', ',', $beitrag);

$firma = $reqJSON['firma'];
$vorname = $reqJSON['vorname'];
$name = $reqJSON['name'];
$strasse = $reqJSON['strasse'];
$hausnr = $reqJSON['hausnr'];
$plz = $reqJSON['plz'];
$ort = $reqJSON['ort'];
$gebdatum = date('Y-m-d', strtotime($reqJSON['gebdatum']));
$email = $reqJSON['email'];
$art = $reqJSON['art'];
$eintritt = date('Y-m-d');

/**
 * Load .env
 */
$dotenv_lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($dotenv_lines as $dotenv_line) {
    if (strpos(trim($dotenv_line), '#') === 0) {
        continue;
    }
    list($dotenv_name, $dotenv_value) = explode('=', $dotenv_line, 2);
    $dotenv_name = trim($dotenv_name);
    $dotenv_value = trim($dotenv_value);
    if (!array_key_exists($dotenv_name, $_SERVER) && !array_key_exists($dotenv_name, $_ENV)) {
        putenv(sprintf('%s=%s', $dotenv_name, $dotenv_value));
        $_ENV[$dotenv_name] = $dotenv_value;
        $_SERVER[$dotenv_name] = $dotenv_value;
    }
}

/**
 * Mail Text Prepare
 */
$do_mail = false;
$mail_subject = "Neuer Mitgliedsantrag - $vorname $name";
$mail_text = <<<EOF
Neuer Mitgliedsantrag

Firma: $firma
Vorname: $vorname
Name: $name
Straße: $strasse
Hausnummer: $hausnr
Postleitzahl: $plz
Ort: $ort
Geburtsdatum: $gebdatum
E-Mail: $email
Mitgliedschaft: $art
Beitrag: $beitrag
Eintritt: $eintritt

Status information:

EOF;

/**
 * Sheet
 */
// Get Access Token
$jwt_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode('{"alg":"RS256","typ":"JWT"}'));
$jwt_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode(array(
    'iss' => 'mitgliedsantrag@centered-scarab-220717.iam.gserviceaccount.com',
    'scope' => 'https://www.googleapis.com/auth/spreadsheets',
    'aud' => 'https://oauth2.googleapis.com/token',
    'exp' => time()+30,
    'iat' => time()
))));
$pkey = openssl_pkey_get_private(base64_decode(getenv('MITGLIEDSANTRAG_SHEET_PKEY', true)));
$jwt_signature = '';
openssl_sign($jwt_header.'.'.$jwt_payload, $jwt_signature, $pkey, OPENSSL_ALGO_SHA256);
openssl_free_key($pkey);
$jwt_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwt_signature));
$req_body = array(
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion' => "$jwt_header.$jwt_payload.$jwt_signature"
);
$options = array(
    'http' => array(
        'header'  => "Accept: application/json\r\nContent-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($req_body)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('https://oauth2.googleapis.com/token', false, $context);
if ($result === false) { 
    $mail_text .= "Sheet failed! Could not retrieve access token.\n";
} else {
    $access_token = json_decode($result, true)['access_token'];

    // Append to sheet
    $req_body = array(
        'range' => 'A1:N1',
        'majorDimension' => 'ROWS',
        'values' => array(array(
            $firma,
            $vorname,
            $name,
            $strasse,
            $hausnr,
            $plz,
            $ort,
            $gebdatum,
            $email,
            $art,
            $beitrag_komma,
            $eintritt
        ))
    );
    $options = array(
        'http' => array(
            'header'  => "Accept: application/json\r\nContent-type: application/json\r\nAuthorization: Bearer $access_token\r\n",
            'method'  => 'POST',
            'content' => json_encode($req_body)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents('https://sheets.googleapis.com/v4/spreadsheets/'.getenv('MITGLIEDSANTRAG_SHEET_ID', true).'/values/A1:N1:append?insertDataOption=INSERT_ROWS&valueInputOption=USER_ENTERED&key='.getenv('MITGLIEDSANTRAG_SHEET_KEY', true), false, $context);
    if ($result === false) {
        $mail_text .= "Sheet failed! Could not append to sheet.\n";
    } else {
        $mail_text .= "Sheet success!\n";
    }
}

/**
 * Invoice
 */
$options = array(
    'http' => array(
        'header'  => "X-Ninja-Token: ".getenv('MITGLIEDSANTRAG_INVOICE_TOKEN', true)."\r\nAccept: application/json\r\n",
        'method'  => 'GET',
        'content' => ''
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('https://invoice.freifunk-aachen.de/api/v1/clients?include=contacts&email='.urlencode($email), false, $context);
if ($result === false) { 
    $mail_text .= "Search clients failed! No client or invoices created\n";
} else {
    $json = json_decode($result, true);
    $client = null;
    foreach($json['data'] as $_client) {
        if($_client['is_deleted'] || $_client['archived_at'] != null)
            continue;
        $client = $_client;
        break;
    }
    if($client != null) {
        // Use existing client
        $client_name = $client['display_name'];
        $client_id = $client['id'];
        $mail_text .= "Invoice client found: $client_name\n";
    } else {
        // Create new client
        $create_client = array(
            'name' => $firma,
            'address1' => $strasse.' '.$hausnr,
            'postal_code' => $plz,
            'city' => $ort,
            'country_id' => '276',
            'contact' => array(
                'first_name' => $vorname,
                'last_name' => $name,
                'email' => $email
            ),
            'state' => '',
            'address2' => '',
            'work_phone' => '',
            'private_notes' => '',
            'website' => '',
            'vat_number' => '',
            'id_number' => ''
        );
        $options = array(
            'http' => array(
                'header'  => "X-Ninja-Token: ".getenv('MITGLIEDSANTRAG_INVOICE_TOKEN', true)."\r\nAccept: application/json\r\nContent-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($create_client)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents('https://invoice.freifunk-aachen.de/api/v1/clients', false, $context);
        if ($result === false) { 
            $mail_text .= "Invoice client failed!\n";
        } else {
            $json = json_decode($result, true);
            $client = $json['data'];
            $client_name = $client['display_name'];
            $client_id = $client['id'];
            $mail_text .= "Invoice client created!\n";
        }
    }
    if($client_id > 0) {
        /**
         * Invoice preparations
         */
        $invoice_items = array();
        if ($art == 'ordentlich') {
            $invoice_items[] = array(
                'product_key' => 'Ordentliche Mitgliedschaft',
                'notes' => 'Mitgliedsbeitrag',
                'cost' => $beitrag,
                'qty' => '1'
            );
        } else {
            $invoice_items[] = array(
                'product_key' => 'Fördernde Mitgliedschaft',
                'notes' => 'Mitgliedsbeitrag',
                'cost' => $beitrag,
                'qty' => '1'
            );
        }
        /**
         * Recurring
         */
        // frequency_id: 9 -- yearly
        // invoice_design_id: 2 -- Beitragsrechnung
        $create_rinvoice = array(
            'client_id' => $client_id,
            'is_recurring' => true,
            'auto_bill_id' => 3,
            'client_enable_auto_bill' => true,
            'frequency_id' => 9,
            'start_date' => bcadd(date('Y'), '1').'-01-01',
            'invoice_items' => $invoice_items,
            'invoice_design_id' => 2
        );
        $options = array(
            'http' => array(
                'header'  => "X-Ninja-Token: ".getenv('MITGLIEDSANTRAG_INVOICE_TOKEN', true)."\r\nAccept: application/json\r\nContent-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($create_rinvoice)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents('https://invoice.freifunk-aachen.de/api/v1/invoices', false, $context);
        if ($result === false) { 
            $mail_text .= "Invoice recurring failed!\n";
        } else {
            $mail_text .= "Invoice recurring success!\n";
        }
        /**
         * Instant
         */
        if($art == 'fördernd' && date('m-d') > '06-30') {
            $invoice_items[0]['cost'] = bcdiv($beitrag, '2', 2);
        }
        // invoice_design_id: 2 -- Beitragsrechnung
        $create_iinvoice = array(
            'client_id' => $client_id,
            'invoice_items' => $invoice_items,
            'invoice_design_id' => 2
        );
        $options = array(
            'http' => array(
                'header'  => "X-Ninja-Token: ".getenv('MITGLIEDSANTRAG_INVOICE_TOKEN', true)."\r\nAccept: application/json\r\nContent-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($create_iinvoice)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents('https://invoice.freifunk-aachen.de/api/v1/invoices', false, $context);
        if ($result === false) { 
            $mail_text .= "Invoice instant failed!\n";
        } else {
            $mail_text .= "Invoice instant success!\n";
        }
    }
}

/**
 * Zammad
 */
// Create user
$create_user = array(
    'firstname' => $vorname,
    'lastname' => $lastname,
    'email' => $email
);
$options = array(
    'http' => array(
        'header'  => "Authorization: Token token=".getenv('MITGLIEDSANTRAG_ZAMMAD_TOKEN', true)."\r\nAccept: application/json\r\nContent-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($create_user)
    )
);
$context  = stream_context_create($options);
file_get_contents('https://support.freifunk-aachen.de/api/v1/users', false, $context);

// Create ticket
$create_ticket = array(
    'group' => 'Mitglieder',
    'title' => $mail_subject,
    'customer_id' => $email,
    'article' => array(
        'subject' => $mail_subject,
        'body' => $mail_text,
        'from' => $email,
        'to' => $email,
        'type' => 'web'
    )
);
$options = array(
    'http' => array(
        'header'  => "X-On-Behalf-Of: $email\r\nAuthorization: Token token=".getenv('MITGLIEDSANTRAG_ZAMMAD_TOKEN', true)."\r\nAccept: application/json\r\nContent-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($create_ticket)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('https://support.freifunk-aachen.de/api/v1/tickets', false, $context);
if ($result === false) {
    $mail_text .= "Zammad ticket failed!\n";
    $do_mail = true;
}

/**
 * Mail
 */
if($do_mail) {
    $status = mail('mitglieder@freifunk-aachen.de', $mail_subject, $mail_text, array(
        'From' => 'mitglieder@freifunk-aachen.de',
        'Reply-To' => "$vorname $name <$email>"
    ));
} else {
    $status = true;
}

/**
 * Response
 */
if($status) {
    header('HTTP/1.1 201 Created');
} else {
    header('HTTP/1.1 500 Internal Server Error');
}
echo(json_encode(array('status' => $status)));
?>
