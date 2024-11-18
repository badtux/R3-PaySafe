
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        /* Global Styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f4f8;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
            text-align: center;
        }

        /* Container Styles */
        .container {
            max-width: 800px;
            padding: 20px;
        }

        /* Title Styles */
        h1 {
            font-size: 6rem;
            color: #3D5EECFF;
            margin: 0;
            font-weight: bold;
        }

        /* Subtitle Styles */
        h2 {
            font-size: 2rem;
            color: #4a4a4a;
            margin-bottom: 20px;
        }

        /* Button Styles */
        .btn {
            padding: 12px 30px;
            background-color: #3D5EECFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.2rem;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #e55a47;
        }

        /* Illustration Styles */
        .illustration {
            margin-top: 30px;
            max-width: 500px;
            width: 100%;
        }

        /* Footer Styles */
        .footer {
            margin-top: 50px;
            color: #9e9e9e;
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            h1 {
                font-size: 5rem;
            }

            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Oops! The page you're looking for doesn't exist.</h2>
        <a href="/" class="btn">Go Back Home</a>

        <div class="footer">
            <p>&copy; 2024 Your Website. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
