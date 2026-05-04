<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | EKiraya Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #04060f;
            font-family: 'Syne', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(7,11,24,0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            padding: 40px 32px;
            width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg,#4f6ef7,#9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 8px;
        }
        .subtitle {
            text-align: center;
            color: #8892a4;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            font-size: 11px;
            font-family: 'DM Mono', monospace;
            color: #8892a4;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-group input {
            width: 100%;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 14px;
            color: #e2e5f0;
            outline: none;
            transition: all 0.2s;
        }
        .input-group input:focus {
            border-color: #4f6ef7;
            background: rgba(79,110,247,0.05);
        }
        .password-requirements {
            font-size: 11px;
            color: #62708c;
            margin-top: 6px;
            font-family: 'DM Mono', monospace;
        }
        button {
            width: 100%;
            background: #4f6ef7;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            font-size: 14px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover {
            background: #3d5ce8;
            transform: translateY(-1px);
        }
        button:disabled {
            background: #62708c;
            cursor: not-allowed;
            transform: none;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #8892a4;
            font-size: 12px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #4f6ef7;
        }
        .error {
            background: rgba(240,68,90,0.15);
            border: 1px solid rgba(240,68,90,0.3);
            border-radius: 10px;
            padding: 10px;
            font-size: 12px;
            color: #f0445a;
            margin-bottom: 16px;
            text-align: center;
        }
        .success {
            background: rgba(31,207,170,0.15);
            border: 1px solid rgba(31,207,170,0.3);
            border-radius: 10px;
            padding: 10px;
            font-size: 12px;
            color: #1fcfaa;
            margin-bottom: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">EKiraya</div>
        <div class="subtitle">Reset Your Password</div>
        
        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif
        
        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif
        
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        
        <form method="POST" action="{{ route('admin.password.update') }}" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            
            <div class="input-group">
                <label>Email</label>
                <input type="email" value="{{ $email }}" disabled style="opacity: 0.6;">
            </div>
            
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="password" id="password" required autofocus placeholder="Enter new password">
                <div class="password-requirements">Minimum 8 characters</div>
            </div>
            
            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required placeholder="Confirm new password">
            </div>
            
            <button type="submit" id="submitBtn">Reset Password</button>
        </form>
        
        <a href="{{ route('admin.login') }}" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>

    <script>
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            
            if (password.length < 8) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Password',
                    text: 'Password must be at least 8 characters long.',
                    background: '#0a1222',
                    color: '#e2e5f0',
                    confirmButtonColor: '#4f6ef7'
                });
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Passwords Do Not Match',
                    text: 'Please make sure both passwords match.',
                    background: '#0a1222',
                    color: '#e2e5f0',
                    confirmButtonColor: '#4f6ef7'
                });
                return false;
            }
            
            // Show loading state
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = 'Resetting...';
        });

        // Show SweetAlert for session messages
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                background: '#0a1222',
                color: '#e2e5f0',
                confirmButtonColor: '#4f6ef7',
                confirmButtonText: 'OK'
            });
        @endif
    </script>
</body>
</html>
