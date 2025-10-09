<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Access Denied</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,600" rel="stylesheet" />
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #0f172a;
                color: #f8fafc;
            }
            .card {
                background: rgba(15, 23, 42, 0.85);
                border-radius: 1rem;
                padding: 2.5rem;
                max-width: 480px;
                width: 90%;
                box-shadow: 0 20px 45px rgba(15, 23, 42, 0.45);
            }
            h1 {
                margin: 0 0 1rem;
                font-size: 2rem;
                font-weight: 600;
            }
            p {
                margin: 0.75rem 0;
                line-height: 1.6;
                color: rgba(248, 250, 252, 0.85);
            }
            .meta {
                margin-top: 1.5rem;
                font-size: 0.875rem;
                color: rgba(148, 163, 184, 0.9);
            }
            code {
                background: rgba(30, 41, 59, 0.8);
                padding: 0.25rem 0.5rem;
                border-radius: 0.5rem;
            }
        </style>
    </head>
    <body>
        <div class="card" role="alert" aria-live="polite">
            <h1>Access denied</h1>
            <p>{{ $message }}</p>
            <p class="meta">Correlation ID: <code>{{ $correlationId }}</code></p>
        </div>
    </body>
</html>
