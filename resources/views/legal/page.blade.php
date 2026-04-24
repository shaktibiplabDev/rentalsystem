<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? $page->title }} | EKiraya</title>
    <meta name="description" content="{{ $page->meta_description ?? '' }}">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #04060f;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            display: inline-block;
            text-decoration: none;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .last-updated {
            color: #8892a4;
            margin-bottom: 2rem;
            font-size: 0.85rem;
        }
        .content {
            color: #8892a4;
            line-height: 1.8;
        }
        .content h2 {
            font-size: 1.3rem;
            margin: 2rem 0 1rem;
            color: #4f6ef7;
        }
        .content h3 {
            font-size: 1.1rem;
            margin: 1.5rem 0 1rem;
            color: #e2e5f0;
        }
        .content p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        .content ul, .content ol {
            margin-left: 2rem;
            margin-bottom: 1rem;
        }
        .content li {
            margin-bottom: 0.5rem;
        }
        .content a {
            color: #4f6ef7;
            text-decoration: none;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: #4f6ef7;
            text-decoration: none;
        }
        hr {
            border-color: rgba(255,255,255,0.05);
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/" class="logo">EKiraya</a>
        <h1>{{ $page->title }}</h1>
        @if($page->published_at)
        <div class="last-updated">Last Updated: {{ $page->published_at->format('F d, Y') }}</div>
        @endif
        <div class="content">
            {!! $page->content !!}
        </div>
        <hr>
        <a href="/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>