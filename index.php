<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading - NebulaBin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            height: 100vh;
            background: #060606;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Arial', sans-serif;
            overflow: hidden;
        }

        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            animation: fadeIn 0.8s ease-in;
        }

        .logo-container {
            position: relative;
            width: 120px;
            height: 120px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top: 3px solid rgba(255, 251, 130, 0.6);
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }

        .spinner::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 2px solid transparent;
            border-top: 2px solid rgba(255, 227, 143, 0.3);
            border-radius: 50%;
            animation: spin 3s linear infinite reverse;
        }

        .loading-text {
            color: #ffffff;
            font-size: 1.2rem;
            font-weight: 300;
            letter-spacing: 2px;
            text-transform: uppercase;
            animation: pulse 2s ease-in-out infinite;
        }

        .loading-dots {
            color: rgba(255, 225, 137, 0.84);
            font-size: 1.2rem;
            animation: dots 1.5s steps(4, end) infinite;
        }

        .progress-bar {
            width: 200px;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 1px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, rgba(255, 228, 148, 0.6), rgba(255, 234, 170, 0.9));
            border-radius: 1px;
            animation: progress 3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }

        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        .fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: scale(0.95); }
        }


    </style>
</head>
<body>
    <div class="loading-container" id="loadingContainer">
        <div class="logo-container">
            <img width="100px" height="100px" src="logo.webp">
            <div class="spinner"></div>
        </div>
        
        <div class="loading-text">
            NebulaBin<span class="loading-dots"></span>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
    </div>

    <script>
        // Simulate loading time and redirect
        window.addEventListener('load', function() {
            // Set loading duration (3 seconds)
            const loadingDuration = 3000;
            
            setTimeout(function() {
                // Add fade out animation
                const container = document.getElementById('loadingContainer');
                container.classList.add('fade-out');
                
                // Redirect after fade out completes
                setTimeout(function() {
                    window.location.href = 'home.php';
                }, 500);
                
            }, loadingDuration);
        });

        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add subtle glitch effect occasionally
            setInterval(function() {
                if (Math.random() < 0.1) { // 10% chance every interval
                    document.body.style.filter = 'hue-rotate(10deg)';
                    setTimeout(function() {
                        document.body.style.filter = 'none';
                    }, 100);
                }
            }, 2000);
        });
    </script>
</body>
</html>