<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | EKiraya</title>
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
            max-width: 1200px;
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
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .subtitle {
            color: #8892a4;
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        .contact-info {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
        }
        .contact-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
        }
        .contact-icon {
            width: 50px;
            height: 50px;
            background: rgba(79,110,247,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .contact-icon i {
            font-size: 1.2rem;
            color: #4f6ef7;
        }
        .contact-detail h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .contact-detail p {
            color: #8892a4;
            font-size: 0.9rem;
        }
        .contact-form {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #8892a4;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
            outline: none;
            transition: all 0.2s;
        }
        input:focus, textarea:focus {
            border-color: #4f6ef7;
            background: rgba(79,110,247,0.05);
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #4f6ef7;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        .btn:hover {
            background: #3d5ce8;
            transform: translateY(-2px);
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: #4f6ef7;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .contact-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/" class="logo">EKiraya</a>
        <h1>Contact Us</h1>
        <div class="subtitle">We'd love to hear from you. Reach out with any questions or feedback.</div>

        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div class="contact-detail">
                        <h3>Email</h3>
                        <p>support@ekiraya.com</p>
                        <p>sales@ekiraya.com</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-phone"></i></div>
                    <div class="contact-detail">
                        <h3>Phone</h3>
                        <p>+91 98765 43210</p>
                        <p>Mon-Fri, 10AM - 6PM IST</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="contact-detail">
                        <h3>Office</h3>
                        <p>Bhubaneswar, Odisha, India</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                    <div class="contact-detail">
                        <h3>WhatsApp</h3>
                        <p>+91 98765 43210</p>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <form method="POST" action="{{ route('contact.submit') }}">
                    @csrf
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn">Send Message →</button>
                </form>
            </div>
        </div>
        <a href="/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>