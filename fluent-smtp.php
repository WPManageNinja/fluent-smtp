<?php
/*
Plugin Name:  FluentSMTP
Plugin URI:   https://fluentsmtp.com
Description:  The Ultimate SMTP Connection Plugin for WordPress.
Version:      1.1.1
Author:       FluentSMTP & WPManageNinja Team
Author URI:   https://fluentsmtp.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  fluent-smtp
Domain Path:  /language
*/

define('FLUENTMAIL_PLUGIN_FILE', __FILE__);

require_once(plugin_dir_path(__FILE__) . 'boot.php');

register_activation_hook(
    __FILE__, array('FluentMail\Includes\Activator', 'handle')
);

register_deactivation_hook(
    __FILE__, array('FluentMail\Includes\Deactivator', 'handle')
);

call_user_func(function () {
    $application = new FluentMail\Includes\Core\Application;
    add_action('plugins_loaded', function () use ($application) {
        do_action('fluentMail_loaded', $application);
    });
});

/*
 * Thanks for checking the source code
 * Please check the full source here: https://github.com/WPManageNinja/fluent-smtp
 * Would love to welcome your pull request
*/


function fsmtpMakeRequest($url, $bodyArgs, $type = 'GET', $headers = false)
{
    if (!$headers) {
        $headers = array(
            'Content-Type'              => 'application/http',
            'Content-Transfer-Encoding' => 'binary',
            'MIME-Version'              => '1.0',
        );
    }

    $args = [
        'headers' => $headers
    ];
    if ($bodyArgs) {
        $args['body'] = json_encode($bodyArgs);
    }


    $args['method'] = $type;
    $request = wp_remote_request($url, $args);

    if (is_wp_error($request)) {
        $message = $request->get_error_message();
        return new \WP_Error(423, $message);
    }

    $body = json_decode(wp_remote_retrieve_body($request), true);

    if (!empty($body['error'])) {
        $error = 'Unknown Error';
        if (isset($body['error_description'])) {
            $error = $body['error_description'];
        } else if (!empty($body['error']['message'])) {
            $error = $body['error']['message'];
        }
        return new \WP_Error(423, $error);
    }

    return $body;
}

add_action('init', function () {
    if (!isset($_GET['gmail'])) {
        return;
    }

    $clientId = '1027873982555-au8paq72le9ug6g51aei7qdarg830qcm.apps.googleusercontent.com';
    $clientSecret = 'VJuEFmdXEbPttVuzbyMmarbN';
    $token = '4/1AY0e-g6h7zSm5nfCeLXoLlog_8KrYTB8gazvfRX7mrT07zR3ufmLOuwFlZs';


//    gmailGetToken($clientId);
//    die();

    //  sendEmailViaGmail(); die();


    $redirect = 'urn:ietf:wg:oauth:2.0:oob';

    $body = [
        'code'          => $token,
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => $redirect,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret
    ];
    $response = fsmtpMakeRequest('https://accounts.google.com/o/oauth2/token', $body, 'POST');

    print_r($response);
    die();

});


function gmailGetToken($clientId)
{
    $authUrl = 'https://accounts.google.com/o/oauth2/auth?access_type=offline&approval_prompt=force&client_id=' . $clientId . '&redirect_uri=urn%3Aietf%3Awg%3Aoauth%3A2.0%3Aoob&response_type=code&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth/gmail.compose';
    wp_redirect($authUrl);
    exit();
}

function sendEmailViaGmail()
{
    $data = [
        'access_token'  => 'ya29.a0AfH6SMBmNEPdigAYbu7mYIGEI5vEf9R2EkESXn-ohfkHXF6pCEAkykam-YF6hhck17_hUBPZKljojLajQ6griONGJ_euus-wtIiYXzxV2n1VpPRrnbnp363GlTPbWJCu_xmFFAWc2of4HsQLm8BlitVcDVAC',
        'refresh_token' => '1//0gAbbL6OQJWsgCgYIARAAGBASNwF-L9Ir3YdMPqRu6AnUhgFdWSokLSEqUMwPkQ9SZzHpA1nM3FSHPSpQ-fFTCWCpsltisvapTBo',
        'expires_in'    => '3599'
    ];

//    $strSubject = 'Test mail using GMail API' . date('M d, Y h:i:s A');
//    $strRawMessage = "From: myAddress<myemail@gmail.com>\r\n";
//    $strRawMessage .= "To: toAddress <recptAddress@gmail.com>\r\n";
//    $strRawMessage .= 'Subject: =?utf-8?B?' . base64_encode($strSubject) . "?=\r\n";
//    $strRawMessage .= "MIME-Version: 1.0\r\n";
//    $strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
//    $strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
//    $strRawMessage .= "this <b>is a test message!\r\n";
    // The message needs to be encoded in Base64URL

    //$mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');


    $token = $data['access_token'];
    $message = "To: support@wpmanageninja.com\r\nFrom: cep.jewel@gmail.com\r\nSubject: GMail test.\r\n My message\r\n";

    $ch = curl_init('https://www.googleapis.com/upload/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer $token", 'Accept: application/json', 'Content-Type: message/rfc822'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
    $data = curl_exec($ch);

    var_dump($data);
    die();


}