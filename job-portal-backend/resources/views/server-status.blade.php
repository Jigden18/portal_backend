<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind CSS (for easy centering) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lottie Web Component Script -->
    <script
      src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js"
      type="module"
    ></script>
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen">

    <!-- Lottie Animation -->
    <dotlottie-wc
        src="https://lottie.host/39fc866b-ee48-4ace-b4ed-8161a59c0947/TEQCkABmpa.lottie"
        style="width: 300px; height: 300px"
        speed="1"
        autoplay
        loop
    ></dotlottie-wc>

    <!-- Optional Label Below -->
    <h1 class="text-2xl font-bold text-[#0F52BA] mt-4">
        Server is Running 
    </h1>

</body>
</html>