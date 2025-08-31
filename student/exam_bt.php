<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Coming Soon</title>
    
    <!-- Using Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Using the Inter font for a clean, modern look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Applying the Inter font across the page */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* A subtle background pattern to match your other pages */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(#d1d5db 1px, transparent 1px);
            background-size: 16px 16px;
            opacity: 0.1;
            z-index: -1;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-lg p-10 space-y-6 bg-white rounded-2xl shadow-2xl shadow-slate-300/60 text-center">
        
        <!-- Icon -->
        <div class="flex justify-center text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-9-5.747h18M5.47 5.47l.354.354m12.416.708l-1.414 1.414M5.47 18.53l.354-.354m12.416-.708l-1.414-1.414M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <!-- Header Section -->
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Coming Soon!</h1>
            <p class="mt-4 text-lg text-slate-600">This mock exam is currently under construction.</p>
            <p class="mt-2 text-slate-500">Our team is working hard to bring it to you. Please check back later.</p>
        </div>

        <!-- Back Button -->
        <div class="pt-6">
            <a href="exam-selection.php" class="inline-block w-full rounded-md bg-[rgb(197,26,29)] px-6 py-3 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-[rgb(177,23,26)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(197,26,29)] transition-all duration-200">
                Return to Exam Selection
            </a>
        </div>
    </div>

</body>
</html>
