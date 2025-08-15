<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lottie Web Component Script -->
    <script
      src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js"
      type="module"
    ></script>

    <style>
        /* Hamburger animation */
        .line {
            transition: all 0.3s ease;
        }
        .hamburger.active .line1 {
            transform: rotate(45deg) translate(5px, 5px);
        }
        .hamburger.active .line2 {
            opacity: 0;
        }
        .hamburger.active .line3 {
            transform: rotate(-45deg) translate(5px, -5px);
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen">

    <!-- Hamburger Menu -->
    <div class="fixed top-5 right-5 z-50">
        <button id="hamburger-btn" class="hamburger flex flex-col justify-between w-8 h-6 cursor-pointer">
            <span class="line line1 h-1 bg-[#0F52BA] rounded"></span>
            <span class="line line2 h-1 bg-[#0F52BA] rounded"></span>
            <span class="line line3 h-1 bg-[#0F52BA] rounded"></span>
        </button>
    </div>

    <!-- Slide-out Menu -->
    <div id="menu" class="fixed top-0 right-0 w-96 h-full bg-white shadow-lg transform translate-x-full transition-transform duration-300 ease-in-out z-40">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-[#0F52BA]">Backend Routes</h2>
        </div>
        <ul id="routes-list" class="p-4 space-y-2 text-[#0F52BA] overflow-y-auto h-[calc(100%-3rem)]">
            <li>Loading routes...</li>
        </ul>
    </div>

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

    <script>
        const btn = document.getElementById("hamburger-btn");
        const menu = document.getElementById("menu");
        const routesList = document.getElementById("routes-list");

        btn.addEventListener("click", () => {
            btn.classList.toggle("active");
            menu.classList.toggle("translate-x-full");
        });

        // Fetch routes dynamically
        fetch('/api/routes')
            .then(res => res.json())
            .then(data => {
                routesList.innerHTML = "";
                data.forEach(route => {
                    const li = document.createElement("li");
                    li.classList.add("hover:bg-gray-100", "p-2", "rounded", "cursor-pointer");
                    li.textContent = `${route.method} - ${route.uri}`;
                    routesList.appendChild(li);
                });
            })
            .catch(() => {
                routesList.innerHTML = "<li class='text-red-500'>Error loading routes</li>";
            });
    </script>
</body>
</html>



<!-- Animations -->
<!-- https://lottie.host/673529d8-1583-4b08-9ce2-31e0cf2b6bb1/woiYlxI9hx.lottie  -->

<!-- [#0F52BA] -->
<!-- https://lottie.host/39fc866b-ee48-4ace-b4ed-8161a59c0947/TEQCkABmpa.lottie -->