<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Test</title>
</head>
<body>
    <main>
        <section>
            <h1>Welcome to our website: {{ config('app.name') }}</h1>
            <h2>Title: {{ $data['title'] }}</h2>
            <h2>Message: {{ $data['message'] }}</h2>
            <p style="color: red">Please do not reply to this email</p>
        </section>
    </main>

    <footer>
        <p>Copyright &copy; 2025 Learn More Academy</p>
    </footer>

</body>
</html>