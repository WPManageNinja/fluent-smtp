<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width">
    <title>FluentSMTP</title>
    <style>
        .fsmtp_wrap {
            margin: 200px auto 0;
            width: 500px;
            text-align: center;
            border: 1px solid gray;
        }

        .fsmtp_title {
            padding: 10px 20px;
            border-bottom: 1px solid gray;
            background: hsl(240deg 8% 93%);
        }

        .fsmtp_body {
            padding: 20px;
            background: white;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: hsl(240deg 6% 87%);
        }
        textarea {
            width: 100%;
            padding: 10px;
        }

        .fsmtp_wrap * {
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="fsmtp_wrap">
        <div class="fsmtp_title"><?php echo esc_html($title); ?></div>
        <div class="fsmtp_body">
            <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
        </div>
    </div>
</body>
</html>
