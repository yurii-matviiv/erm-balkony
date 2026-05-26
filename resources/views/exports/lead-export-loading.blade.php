<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <title>Preparing CSV export...</title>

    <style>

        /**
         * ---------------------------------------------------------
         * PAGE
         * ---------------------------------------------------------
         */
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: white;
            font-family: Arial, sans-serif;
        }

        /**
         * ---------------------------------------------------------
         * CONTENT WRAPPER
         * ---------------------------------------------------------
         */
        .wrapper {
            text-align: center;
            max-width: 500px;
            padding: 30px;
        }

        /**
         * ---------------------------------------------------------
         * SPINNER
         * ---------------------------------------------------------
         */
        .spinner {
            width: 64px;
            height: 64px;
            border: 5px solid rgba(255,255,255,0.15);
            border-top-color: #facc15;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }

        /**
         * ---------------------------------------------------------
         * SPINNER ANIMATION
         * ---------------------------------------------------------
         */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /**
         * ---------------------------------------------------------
         * TITLE
         * ---------------------------------------------------------
         */
        h1 {
            margin: 0 0 14px;
            font-size: 24px;
            font-weight: 700;
        }

        /**
         * ---------------------------------------------------------
         * DESCRIPTION
         * ---------------------------------------------------------
         */
        p {
            margin: 0;
            opacity: 0.75;
            line-height: 1.6;
            font-size: 15px;
        }

    </style>

</head>

<body>

<div class="wrapper">

    <div class="spinner"></div>

    <h1>Preparing CSV export...</h1>

    <p>
        Please wait while the browser generates and downloads the CSV file.
        <br><br>
        This window will close automatically in a few seconds.
    </p>

</div>

<script>

    /**
     * ---------------------------------------------------------
     * START DOWNLOAD
     * ---------------------------------------------------------
     */
    setTimeout(() => {

        window.location.href = "{{ route('lead-export.csv') }}";

    }, 300);

    /**
     * ---------------------------------------------------------
     * AUTO CLOSE WINDOW
     * ---------------------------------------------------------
     */
    setTimeout(() => {

        window.close();

    }, 5000);

</script>

</body>
</html>